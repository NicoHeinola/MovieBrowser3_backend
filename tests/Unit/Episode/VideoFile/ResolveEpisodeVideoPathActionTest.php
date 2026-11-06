<?php

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowTitle\ShowTitle;

function episodeForPath(string $episodeName = 'Episode 1', string $filename = 'ep1.mkv', ?string $primaryTitle = 'Dr. Stone', string $entryName = 'Season 1'): Episode
{
    $show = (new Show)->forceFill(['id' => 3]);
    $titles = $primaryTitle === null ? [] : [(new ShowTitle)->forceFill(['title' => $primaryTitle, 'is_primary' => true])];
    $show->setRelation('titles', collect([(new ShowTitle)->forceFill(['title' => 'Alias', 'is_primary' => false]), ...$titles]));

    $entry = (new ShowEntry)->forceFill(['id' => 5, 'name' => $entryName]);
    $entry->setRelation('show', $show);

    $episode = (new Episode)->forceFill(['id' => 8, 'name' => $episodeName, 'filename' => $filename]);
    $episode->setRelation('entry', $entry);

    return $episode;
}

it('builds the path from ids, names and the stored extension', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(), basePath: '/videos/'))
        ->toBe('/videos/3_Dr. Stone/5_Season 1/8_Episode 1.mkv');
});

it('uses the show id when there is no primary title', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(primaryTitle: null), basePath: '/videos'))
        ->toBe('/videos/3_3/5_Season 1/8_Episode 1.mkv');
});

it('replaces slashes and collapses whitespace in every name', function () {
    $episode = episodeForPath(episodeName: 'A/B\\C', primaryTitle: "Fate / Zero\t2", entryName: '  Part   1  ');

    expect(ResolveEpisodeVideoPathAction::run($episode, basePath: '/videos'))
        ->toBe('/videos/3_Fate - Zero 2/5_Part 1/8_A-B-C.mkv');
});

it('replaces characters windows does not allow in every name', function () {
    $episode = episodeForPath(episodeName: 'What? *Really*', primaryTitle: 'Re:Zero', entryName: 'Special: <1> | "2"');

    expect(ResolveEpisodeVideoPathAction::run($episode, basePath: '/videos'))
        ->toBe('/videos/3_Re-Zero/5_Special- -1- - -2-/8_What- -Really-.mkv');
});

it('removes trailing dots and spaces from every name', function () {
    $episode = episodeForPath(episodeName: 'The end...', primaryTitle: 'Ea harum aut recusandae.', entryName: 'Season 1 . ');

    expect(ResolveEpisodeVideoPathAction::run($episode, basePath: '/videos'))
        ->toBe('/videos/3_Ea harum aut recusandae/5_Season 1/8_The end.mkv');
});

it('keeps dots inside a name', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(primaryTitle: 'Mr. Robot v1.2'), basePath: '/videos'))
        ->toBe('/videos/3_Mr. Robot v1.2/5_Season 1/8_Episode 1.mkv');
});

it('falls back to untitled for a name of only dots', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(episodeName: '...'), basePath: '/videos'))
        ->toBe('/videos/3_Dr. Stone/5_Season 1/8_untitled.mkv');
});

it('falls back to untitled for names that are empty after cleaning', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(episodeName: '   ', entryName: ''), basePath: '/videos'))
        ->toBe('/videos/3_Dr. Stone/5_untitled/8_untitled.mkv');
});

it('omits the extension when the stored filename has none', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(filename: 'raw'), basePath: '/videos'))
        ->toBe('/videos/3_Dr. Stone/5_Season 1/8_Episode 1');
});

it('returns the episode folder when there is no video yet', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(filename: ''), basePath: '/videos'))
        ->toBe('/videos/3_Dr. Stone/5_Season 1');
});

it('applies show, entry and filename overrides for before and after paths', function () {
    expect(ResolveEpisodeVideoPathAction::run(episodeForPath(), basePath: '/new', showName: 'Old Title', entryName: 'Old Season', filename: 'x.mp4'))
        ->toBe('/new/3_Old Title/5_Old Season/8_Episode 1.mp4');
});
