<?php

namespace App\Actions\Episode\VideoFile;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads the length a Matroska file (.mkv, .webm) stores in its header, touching only a few kilobytes: on a Windows
 * drive under WSL a full parse (getID3) reads far more and takes around 100 times longer.
 *
 * How an mkv file is laid out:
 *
 * - The whole file is a tree of "elements". Each one is: an id (what it is), a size (how many bytes its body has) and
 *   the body. A body is either raw data (a number, a text) or more elements inside it.
 * - Ids and sizes are "variable-length integers": 1 to 8 bytes, where the first byte says how many bytes there are.
 * - Because every element states its size, anything uninteresting can be skipped by jumping over its body unread.
 *
 * The parts this reader cares about, in file order:
 *
 *     EBML header          "this is a Matroska file"; skipped
 *     Segment              holds everything else
 *       SeekHead           a table of contents: where each part below starts
 *       Info               file details
 *         TimestampScale   how many nanoseconds one "tick" is (usually 1 ms)
 *         Duration         the length, in ticks
 *       Tracks, Clusters…  the actual video and audio, never read
 *
 * So the length in seconds is Duration × TimestampScale / 1 000 000 000.
 */
class ReadMatroskaDurationAction
{
    use AsAction;

    // Element ids, as the Matroska spec defines them

    private const int EBML_HEADER = 0x1A45DFA3;

    private const int SEGMENT = 0x18538067;

    private const int SEEK_HEAD = 0x114D9B74;

    /** One SeekHead entry: which element (SeekID) starts where (SeekPosition). */
    private const int SEEK = 0x4DBB;

    private const int SEEK_ID = 0x53AB;

    private const int SEEK_POSITION = 0x53AC;

    private const int INFO = 0x1549A966;

    private const int TIMESTAMP_SCALE = 0x2AD7B1;

    private const int DURATION = 0x4489;

    /** Nanoseconds per timestamp tick when the file doesn't say: 1 ms. */
    private const int DEFAULT_TIMESTAMP_SCALE = 1_000_000;

    /** Top-level elements to step over before giving up; Info comes within the first few in practice. */
    private const int MAX_TOP_LEVEL_ELEMENTS = 64;

    /** The largest field body this reader loads. The numbers it needs are at most 8 bytes; more means a corrupt size. */
    private const int MAX_BODY_BYTES = 64;

    /**
     * The length in seconds, or null when the file isn't Matroska or stores no length.
     */
    public function handle(string $path): ?float
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            return $this->readDuration($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Walks from the start of the file to the Info element and reads the length from it.
     *
     * @param  resource  $handle
     */
    private function readDuration($handle): ?float
    {
        // Every Matroska file starts with the EBML header; anything else isn't one
        $header = $this->readElementHeader($handle);

        if ($header === null || $header['id'] !== self::EBML_HEADER || $header['unknownSize']) {
            return null;
        }

        // Jump over the header's body to the Segment that follows it
        fseek($handle, $header['start'] + $header['size']);

        $segment = $this->readElementHeader($handle);

        if ($segment === null || $segment['id'] !== self::SEGMENT) {
            return null;
        }

        // Now inside the Segment, at its first child. SeekHead positions count from here
        $segmentStart = $segment['start'];
        $followedSeekHead = false;

        // Look at the Segment's children one by one until Info turns up
        for ($i = 0; $i < self::MAX_TOP_LEVEL_ELEMENTS; $i++) {
            $element = $this->readElementHeader($handle);

            if ($element === null) {
                return null;
            }

            if ($element['id'] === self::INFO) {
                return $this->readInfoDuration($handle, $element['start'] + $element['size']);
            }

            // Info usually comes right after the SeekHead, but when it doesn't, the SeekHead says where it is.
            // Followed once, so a broken table can't send the loop in circles.
            if ($element['id'] === self::SEEK_HEAD && !$followedSeekHead) {
                $infoPosition = $this->readInfoPosition($handle, $element['start'] + $element['size']);

                if ($infoPosition !== null) {
                    $followedSeekHead = true;
                    fseek($handle, $segmentStart + $infoPosition);

                    continue;
                }
            }

            // An element of unknown size (a live stream's cluster) has no end to jump to
            if ($element['unknownSize']) {
                return null;
            }

            // Not Info: jump over its body to the next child
            fseek($handle, $element['start'] + $element['size']);
        }

        return null;
    }

    /**
     * Reads TimestampScale and Duration from Info's children and turns them into seconds.
     *
     * @param  resource  $handle  positioned at Info's first child
     * @param  int  $end  where Info's body ends
     */
    private function readInfoDuration($handle, int $end): ?float
    {
        $scale = self::DEFAULT_TIMESTAMP_SCALE;
        $duration = null;

        while (ftell($handle) < $end && ($child = $this->readElementHeader($handle)) !== null) {
            // Only these two fields matter. The rest, such as a release title, can be long, so step over them unread
            if ($child['id'] !== self::TIMESTAMP_SCALE && $child['id'] !== self::DURATION) {
                fseek($handle, $child['start'] + $child['size']);

                continue;
            }

            $body = $this->readBody($handle, $child['size']);

            if ($body === null) {
                return null;
            }

            // A plain whole number, in nanoseconds per tick
            if ($child['id'] === self::TIMESTAMP_SCALE) {
                $scale = $this->unsignedInteger($body);
            }

            // A 4 or 8 byte big-endian float, in ticks
            if ($child['id'] === self::DURATION) {
                $duration = match (strlen($body)) {
                    4 => unpack('G', $body)[1],
                    8 => unpack('E', $body)[1],
                    default => null,
                };
            }
        }

        if ($duration === null || $scale <= 0) {
            return null;
        }

        // Ticks × nanoseconds per tick = nanoseconds, then to seconds
        return $duration * $scale / 1_000_000_000;
    }

    /**
     * Where Info starts, relative to the segment's body, from the SeekHead's entries.
     *
     * @param  resource  $handle  positioned at the SeekHead's first entry
     * @param  int  $end  where the SeekHead's body ends
     */
    private function readInfoPosition($handle, int $end): ?int
    {
        // Each Seek entry pairs an element id with where that element starts
        while (ftell($handle) < $end && ($seek = $this->readElementHeader($handle)) !== null) {
            $seekEnd = $seek['start'] + $seek['size'];

            // Padding and other non-entries: skip
            if ($seek['id'] !== self::SEEK) {
                fseek($handle, $seekEnd);

                continue;
            }

            $id = null;
            $position = null;

            // Read the entry's two fields, in whichever order they come
            while (ftell($handle) < $seekEnd && ($child = $this->readElementHeader($handle)) !== null) {
                $body = $this->readBody($handle, $child['size']);

                if ($body === null) {
                    return null;
                }

                if ($child['id'] === self::SEEK_ID) {
                    $id = $this->unsignedInteger($body);
                }

                if ($child['id'] === self::SEEK_POSITION) {
                    $position = $this->unsignedInteger($body);
                }
            }

            // Only the entry pointing at Info is useful here
            if ($id === self::INFO && $position !== null) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Reads an element's id and size, leaving the file positioned at the start of its body.
     *
     * @param  resource  $handle
     * @return array{id: int, size: int, unknownSize: bool, start: int}|null `start` is where the body begins
     */
    private function readElementHeader($handle): ?array
    {
        // Ids are compared with their length marker bit still in (0x1A45DFA3, as the spec writes them); sizes drop it
        $id = $this->readVariableInteger($handle, keepMarker: true);
        $size = $id === null ? null : $this->readVariableInteger($handle, keepMarker: false);

        if ($size === null) {
            return null;
        }

        return ['id' => $id['value'], 'size' => $size['value'], 'unknownSize' => $size['allOnes'], 'start' => (int) ftell($handle)];
    }

    /**
     * Reads an EBML variable-length integer. The number of leading zero bits in the first byte says how many more
     * bytes follow: `1xxxxxxx` is 1 byte in total, `01xxxxxx` 2 bytes, up to `00000001` for 8. That first 1 bit is the
     * "length marker"; the bits after it and the following bytes hold the value.
     *
     * @param  resource  $handle
     * @return array{value: int, allOnes: bool}|null `allOnes` marks a size of "unknown"
     */
    private function readVariableInteger($handle, bool $keepMarker): ?array
    {
        $first = fread($handle, 1);

        if ($first === false || $first === '') {
            return null;
        }

        $firstByte = ord($first);

        // Count the bytes: find the first 1 bit, from the left
        $length = 1;

        while ($length <= 8 && ($firstByte & (0x80 >> ($length - 1))) === 0) {
            $length++;
        }

        // A first byte of 0 has no marker at all: not valid EBML
        if ($length > 8) {
            return null;
        }

        // The bits after the marker are the value's first bits, e.g. 0x3F (00111111) for a 2 byte number
        $valueMask = 0xFF >> $length;
        $value = $keepMarker ? $firstByte : $firstByte & $valueMask;
        // A size with every value bit set means "unknown", used by streamed files
        $allOnes = ($firstByte & $valueMask) === $valueMask;

        $rest = $length > 1 ? $this->readBody($handle, $length - 1) : '';

        if ($rest === null) {
            return null;
        }

        // Append the remaining bytes, most significant first
        foreach (str_split($rest) as $byte) {
            $value = ($value << 8) | ord($byte);
            $allOnes = $allOnes && ord($byte) === 0xFF;
        }

        // Only sizes can be "unknown"; an id that happens to be all ones is just an id
        return ['value' => $value, 'allOnes' => $allOnes && !$keepMarker];
    }

    /**
     * Reads a small field body, or null when the file ends early or the size is too big to be a real field.
     *
     * @param  resource  $handle
     */
    private function readBody($handle, int $size): ?string
    {
        if ($size === 0) {
            return '';
        }

        if ($size > self::MAX_BODY_BYTES) {
            return null;
        }

        $body = fread($handle, $size);

        return $body !== false && strlen($body) === $size ? $body : null;
    }

    /**
     * A big-endian whole number, as Matroska stores ids, positions and the timestamp scale.
     */
    private function unsignedInteger(string $bytes): int
    {
        $value = 0;

        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
        }

        return $value;
    }
}
