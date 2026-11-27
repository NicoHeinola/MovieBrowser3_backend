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
| Media folder 1, 2, … | The folders that hold your videos, as in step 5 (`/mnt/g/New_Vid`). An empty line finishes. |
| Backup folder | Where the database backups go. A folder on another drive is safest (`/mnt/g/MovieBrowser3_backups`), so one broken disk can't take both. Enter keeps `backups` in the install folder. |
| App port | Enter (8780). |
| Reachable from other devices on the network? | `y` to use MovieBrowser3 from a phone or another PC at home, otherwise `n`. |
| Database port, host bridge port | Enter. |
| Admin username and password | The login you'll use in MovieBrowser3. Only asked the first time. |

When it's done it prints the app's address, `http://localhost:8780`, and where it put the update shortcut, `update_movie_browser_3.bat`. Copy that file anywhere you like, for example to your desktop.

## 7. First Login

1. Open http://localhost:8780 in your browser and log in with the admin username and password.
2. A setup wizard opens. It asks for these and suggests an answer for each:
   - **Video base folder**: where new videos are stored. It must be inside one of your media folders, e.g. `/mnt/g/New_Vid/MovieBrowser3`.
   - **VLC**: where VLC is, usually `/mnt/c/Program Files/VideoLAN/VLC/vlc.exe`. The wizard finds it by itself when it's installed in the usual place.
   - **Downloads** (optional): see [Downloading Torrents](#downloading-torrents). Leave the address empty to skip it.

   The wizard checks each before saving and says what's wrong if something doesn't work.

To change them later, go to **Admin → Settings**, or reopen the wizard with **Admin → Settings → Run setup again**.

## Downloading Torrents

MovieBrowser3 can download torrents straight into shows through your own qBittorrent on Windows. It's optional.

1. In qBittorrent, open **Tools → Options → Web UI**, tick **Web User Interface (Remote control)**, and set a username and password. Note the port (8080 by default).
2. Pick a download folder, preferably on a disk other than the videos' and Windows' own, so heavy downloading doesn't slow them down. Its drive must be a media folder (step 5 and 6): run the installer again and add it, e.g. `/mnt/d`.
3. In **Admin → Settings → Downloads**, enter qBittorrent's address (`http://127.0.0.1:8080`), the username, the password and the download folder (`/mnt/d/MovieBrowser3 Downloads`), then Save. It checks the login and the folder first.

Then send a torrent into a show from **Admin → Torrents**, pick its files for episodes in the show editor and save. **Admin → Downloads** shows the progress. Within a minute of a file finishing, it's copied into its episode and plays. qBittorrent decides how long to seed (its share limit): once it stops a finished torrent, MovieBrowser3 removes it and its files from the download folder.

## Everyday Use

- After you restart the PC and log in to Windows, MovieBrowser3 starts by itself within a minute. You don't need to open Ubuntu.
- To update MovieBrowser3, double-click `update_movie_browser_3.bat`. The window shows what changed and stays open until you press a key. If an update fails, the previous version keeps running.
- To change an answer (more media folders, the backup folder, network access, ports), run the installer from step 6 again. It remembers your answers.

From the Ubuntu window you can also run:

```bash
moviebrowser3 update     # the same as the .bat
moviebrowser3 status     # is everything running?
moviebrowser3 logs       # what's happening (Ctrl+C to stop watching)
moviebrowser3 restart    # restart everything
moviebrowser3 backup     # back up the database now
```

## Backups

MovieBrowser3 backs up its database (your shows, episodes, watch progress, users and settings, not the videos) into the backup folder once a day while the PC is on, and before every update. It keeps the newest 14. Admins see them, and can start one, in MovieBrowser3's settings.

To go back to a backup, run `moviebrowser3 restore <name>` with a name from that list, e.g. `moviebrowser3 restore moviebrowser3-20261115-030000.dump`. It replaces everything since that backup, so it asks you to type `yes` first, and it backs up the current database before restoring.

## When Something Goes Wrong

| Problem | What to do |
| --- | --- |
| The page doesn't load | Run `moviebrowser3 status`. If something isn't "Up", run `moviebrowser3 restart`. |
| Videos are missing | The disk isn't visible to Ubuntu: see step 5, then run `moviebrowser3 restart`. |
| Playing says "The host bridge … did not answer" | Run `moviebrowser3 bridge start`. Or open Task Scheduler and run **MovieBrowser3 host bridge**. |
| VLC opens, but MovieBrowser3 doesn't notice what's playing | Mirrored networking is off: redo step 2. |
| The update stops with a red ✖ | It shows the end of the log, and the whole log is in `~/prod_codes/MovieBrowser3/install.log`. To watch every command's output live, run `MOVIEBROWSER3_VERBOSE=1 moviebrowser3 update`. The previous version keeps running. |
| The update says "can't be fast-forwarded" or "can't check out release" | Someone changed files in the install folder. Ask a developer, or see TECHNICAL.md. |
| The newest backup in Settings is days old | The backup folder's drive isn't visible to Ubuntu: see step 5, then run `moviebrowser3 restart` and `moviebrowser3 backup`. |
| The update says the database backup failed | The same: the backup folder's drive isn't mounted. Nothing was changed; mount it and update again. |
| Settings says qBittorrent "didn't answer" | qBittorrent isn't running, or its Web UI is off: see [Downloading Torrents](#downloading-torrents). Mirrored networking must be on (step 2). |
| A download says "a finished file isn't in the download folder" | The download folder's drive isn't a media folder: add it with the installer (step 6), then **Try again** on the download. |
| `moviebrowser3: command not found` | Close and reopen Ubuntu. If it still happens, run `~/prod_codes/MovieBrowser3/moviebrowser3` with the full path. |
