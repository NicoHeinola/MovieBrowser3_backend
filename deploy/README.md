# Installing MovieBrowser3

This guide sets up MovieBrowser3 on a Windows PC, step by step. You don't need to be a developer: each step says what to type and where. It takes about 30 minutes, most of it waiting for downloads.

How it works, file by file, is in [TECHNICAL.md](TECHNICAL.md).

## What You Need

- Windows 11 (version 22H2 or newer). Check in Settings → System → About.
- Administrator rights on the PC, once, for the first step.
- About 10 GB of free disk space, and an internet connection.
- Your videos on a drive Windows can see: an internal disk, or an external one with a drive letter.

## 1. Install WSL2

WSL2 runs Linux inside Windows. MovieBrowser3 runs there.

1. Click Start, type **PowerShell**, right-click **Windows PowerShell** and choose **Run as administrator**.
2. Type this and press Enter:

   ```powershell
   wsl --install
   ```

3. Restart the PC when it asks.
4. After the restart an **Ubuntu** window opens. If it doesn't, click Start and open **Ubuntu**. Choose a username and a password. Remember the password: Ubuntu asks for it when a command starts with `sudo`. It stays invisible while you type.

## 2. Turn On Mirrored Networking

This lets MovieBrowser3 talk to VLC on Windows.

1. Open PowerShell (not as administrator this time) and type:

   ```powershell
   notepad $env:USERPROFILE\.wslconfig
   ```

   If Notepad asks to create the file, choose **Yes**.
2. Put these two lines in the file, then save it and close Notepad:

   ```ini
   [wsl2]
   networkingMode=mirrored
   ```

3. Back in PowerShell, restart WSL:

   ```powershell
   wsl --shutdown
   ```

4. Open Ubuntu again and check it. It should say `mirrored`:

   ```bash
   wslinfo --networking-mode
   ```

## 3. Install Docker and the Tools

Type these in the **Ubuntu** window, one line at a time:

```bash
sudo apt update && sudo apt install -y git python3 curl
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```

Close the Ubuntu window. In PowerShell run `wsl --shutdown`, then open Ubuntu again. Check that Docker works; it should print "Hello from Docker!":

```bash
docker run --rm hello-world
```

Use this Docker (Docker Engine inside Ubuntu), not Docker Desktop.

## 4. Install VLC on Windows

Install VLC from [videolan.org](https://www.videolan.org/). MovieBrowser3 opens your videos in it. The usual location is `C:\Program Files\VideoLAN\VLC\vlc.exe`; you'll need that later.

## 5. Make Your Video Folders Visible

Ubuntu sees each Windows drive under `/mnt/` plus its letter in lowercase:

| In Windows | In Ubuntu |
| --- | --- |
| `G:\New_Vid` | `/mnt/g/New_Vid` |
| `C:\Program Files\VideoLAN\VLC\vlc.exe` | `/mnt/c/Program Files/VideoLAN/VLC/vlc.exe` |

Check that you can see your videos, using your own drive letter and folder:

```bash
ls /mnt/g/New_Vid
```

**External disks** only show up if they were plugged in when Ubuntu started. If `ls` says "No such file or directory", or shows nothing:

1. Give the disk a fixed drive letter, so the path never changes. In Windows, right-click Start → **Disk Management** → right-click the disk → **Change Drive Letter and Paths**.
2. Make Ubuntu mount it every time it starts. Replace `g` and `G:` with your letter in both places:

   ```bash
   sudo mkdir -p /mnt/g
   echo 'G: /mnt/g drvfs defaults,uid=1000,gid=1000,nofail 0 0' | sudo tee -a /etc/fstab
   ```

3. Mount it right now too:

   ```bash
   sudo mount /mnt/g
   ```

If you plug the disk in later, while MovieBrowser3 is running, mount it with `sudo mount /mnt/g` and then run `moviebrowser3 restart`.

## 6. Run the Installer

In Ubuntu:

```bash
curl -fsSL https://raw.githubusercontent.com/NicoHeinola/MovieBrowser3_backend/main/deploy/install.sh | bash
```

It asks a few questions. Each answer is filled in already: press Enter to keep it, or change it first. The Tab key completes folder names.

| Question | What to answer |
| --- | --- |
| Install folder | Enter keeps the default. |
| Backend repo, Frontend repo | Enter. |
| Media folder 1, 2, … | Your video folders, as in step 5 (`/mnt/g/New_Vid`). An empty line finishes. |
| App port | Enter (8780). |
| Reachable from other devices on the network? | `y` to use MovieBrowser3 from a phone or another PC at home, otherwise `n`. |
| Database port, VLC bridge port | Enter. |
| Admin username and password | The login you'll use in MovieBrowser3. Only asked the first time. |

When it's done it prints the app's address, `http://localhost:8780`, and where it put the update shortcut, `update_movie_browser_3.bat`. Copy that file anywhere you like, for example to your desktop.

## 7. First Login

1. Open http://localhost:8780 in your browser and log in with the admin username and password.
2. A setup wizard opens. It asks for two things and suggests an answer for each:
   - **Video folder**: where new videos are stored. It must be inside one of your media folders, e.g. `/mnt/g/New_Vid/MovieBrowser3`.
   - **VLC**: where VLC is, usually `/mnt/c/Program Files/VideoLAN/VLC/vlc.exe`. The wizard finds it by itself when it's installed in the usual place.

   The wizard checks both before saving and says what's wrong if something doesn't work.

To change them later, go to **Admin → Settings**, or reopen the wizard with **Admin → Settings → Run setup again**.

## Moving From MovieBrowser2

MovieBrowser3 can import your MovieBrowser2 shows: titles, images, descriptions, categories, seasons, episodes and your watch statuses. It **copies** the videos into the video folder from step 7 and only reads the originals, so MovieBrowser2 keeps working. Websites aren't imported.

1. Save the JSON from MovieBrowser2's export endpoint anywhere Ubuntu can see it, e.g. `G:\New_Vid\movies.json` (`/mnt/g/New_Vid/movies.json`).
2. Check first. Pass the export, then MovieBrowser2's video folder. This copies nothing: it lists missing videos and the size to copy, and checks there's enough free space:

   ```bash
   moviebrowser3 import-moviebrowser2 /mnt/g/New_Vid/movies.json /mnt/g/New_Vid --dry-run
   ```

   **Videos missing** should be close to 0, and **Copy into** should be your video folder from step 7. If no video is found at all, it stops and says so.
3. Import. It asks before it starts, then goes one show at a time:

   ```bash
   moviebrowser3 import-moviebrowser2 /mnt/g/New_Vid/movies.json /mnt/g/New_Vid
   ```

- The MovieBrowser2 folder doesn't need to be a media folder: this command shows it to MovieBrowser3 read-only, for the import only, so the originals can't be changed.
- Copying a lot takes hours. Keep the Ubuntu window open. Ctrl+C stops after the current video and undoes the show in progress.
- A show that fails is undone and the others carry on. Run the same command again to retry: shows already imported are skipped.
- If a video becomes unreadable during the import (an external disk dropping out), that show fails and is undone. Reconnect the disk, run `moviebrowser3 restart`, and run the command again.
- The check also lists shows that were imported with fewer videos than the originals. Add `--redo-incomplete` to delete those shows (only their MovieBrowser3 copies) and import them again.
- `--only=12 --only=15` imports just those MovieBrowser2 show ids, e.g. to try one first or when the disk is short on space.
- Watch statuses go to the MovieBrowser3 user with the same username (ignoring upper and lower case).

## Everyday Use

- After you restart the PC and log in to Windows, MovieBrowser3 starts by itself within a minute. You don't need to open Ubuntu.
- To update MovieBrowser3, double-click `update_movie_browser_3.bat`. The window shows what changed and stays open until you press a key. If an update fails, the previous version keeps running.
- To change an answer (more media folders, network access, ports), run the installer from step 6 again. It remembers your answers.

From the Ubuntu window you can also run:

```bash
moviebrowser3 update     # the same as the .bat
moviebrowser3 status     # is everything running?
moviebrowser3 logs       # what's happening (Ctrl+C to stop watching)
moviebrowser3 restart    # restart everything
```

## When Something Goes Wrong

| Problem | What to do |
| --- | --- |
| The page doesn't load | Run `moviebrowser3 status`. If something isn't "Up", run `moviebrowser3 restart`. |
| Videos are missing | The disk isn't visible to Ubuntu: see step 5, then run `moviebrowser3 restart`. |
| Playing says "The VLC bridge … did not answer" | Run `moviebrowser3 bridge start`. Or open Task Scheduler and run **MovieBrowser3 VLC bridge**. |
| VLC opens, but MovieBrowser3 doesn't notice what's playing | Mirrored networking is off: redo step 2. |
| The update stops with a red ✖ | It shows the end of the log, and the whole log is in `~/prod_codes/MovieBrowser3/install.log`. To watch every command's output live, run `MOVIEBROWSER3_VERBOSE=1 moviebrowser3 update`. The previous version keeps running. |
| The update says "can't be fast-forwarded" or "can't check out release" | Someone changed files in the install folder. Ask a developer, or see TECHNICAL.md. |
| `moviebrowser3: command not found` | Close and reopen Ubuntu. If it still happens, run `~/prod_codes/MovieBrowser3/moviebrowser3` with the full path. |
