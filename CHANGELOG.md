# Changelog

Each version's section is the text of its GitHub release (`dev/release.sh`).

## 1.4.6
- Text and blog boxes with a fixed height can scroll instead of cutting off what does not fit. In a blog box only the posts scroll; the title and "All posts" stay in place.
- Containers can have a fixed height too, and their boxes then scroll inside it. On phones the height is dropped.

## 1.4.5
- A blog box can show the whole text of each post, not only the title or the first paragraph. Choose under "Under each title" in the box.
- Editors and administrators get an "Edit" link on every box and post on the site. "Save and close" brings you back to the page. The links can be switched off under Settings.
- Logged-in users get a link to the admin area in the menu bar: "Admin" for editors and administrators, "Account" for other users.
- A post with comments closed and no comments no longer mentions comments at all.

## 1.4.4
- The box editor has two save buttons: "Save" keeps you in the box, "Save and close" returns to the page.

## 1.4.3
- Text boxes accept HTML next to Markdown, for example `<a href="https://..." target="_blank">` for a link that opens in a new tab. Scripts and other unsafe parts are removed when the page is shown.
- After "Move…" you stay on the page the box was moved from, and the message says where it went.

## 1.4.2
- Admin: the buttons at the top of the blog and post screens have a gap below them, and the three blog buttons share one style, so they no longer look like tabs.

## 1.4.1
- On narrow screens the menu bar shows a "Menu" button that opens the pages as a list, instead of wrapping the links.

## 1.4.0
- Maintenance mode: a switch under Settings shows visitors a notice while editors keep working.
- Updates from GitHub: the Update screen checks for a new release, installs it with one click and can restore the previous version.

## 1.3.0
- Editors manage all content; administrators manage the site.
- The workbench: a hidden page for boxes that are not on any page.
- "Move…" moves a box to any row on any page; rows offer "move here from the workbench".
- Deleting a container asks whether to keep its boxes on the workbench.

## 1.2.0
- Container boxes: a box that holds rows of boxes, so boxes can be stacked beside a tall one.
- Installed sites upgrade their database by themselves after new files are uploaded.

## 1.1.1
- Up and down arrows for the page order in the pages list.

## 1.1.0
- The site can live in a subfolder of the web root.

## 1.0.0
- First release.
