# Quick Thread — MyBB Plugin

<p align="center">
  <img src=".github/quick-thread-cover.svg" alt="Quick Thread for MyBB — inline thread composer and forum chooser" width="100%">
</p>

Inline, expanding thread-creation form for forum display pages, plus a forum chooser overlay on the board index. Inspired by XenForo's quick-thread box.

The form starts as a subject input in the thread list. Focusing it expands a full editor with MyCode buttons, smilies, post options, a poll tab, and a submit button. Threads can be submitted through MyBB's normal `newthread.php` flow or the plugin's AJAX handler, depending on the **AJAX Only Mode** setting.

## Features

- **Inline thread composer** injected into the forum thread list; expands on focus, with optional animation.
- **MyBB editor integration** with SCEditor, MyCode toolbar, and clickable smilies subject to forum/user settings.
- **Message and poll tabs**, prefix selection, and signature, subscription, and smilie post options.
- **Guest handling** with login/register notice and captcha markup when enabled.
- **Two submission modes**: standard MyBB POST or AJAX submission with validation feedback and redirects.
- **Forum chooser overlay** on the board index for logged-in users.
- **Permission checks and configuration** including master switch, forum allow-list, posting/view permissions, and forum state.
- **PluginLibrary integration** for settings, templates, and stylesheet registration.

## Requirements

| Requirement | Version / note |
| --- | --- |
| MyBB | 1.8.x (`compatibility` is `18*`) |
| PHP | 7.4+ |
| PluginLibrary | Required; bundled at `inc/plugins/pluginlibrary.php` |
| SCEditor and jQuery | Stock MyBB assets |

The plugin adds no database tables, admin module, or core file edits. Settings and templates are managed through MyBB and PluginLibrary.

## Installation

1. Extract the repository into your MyBB root (the directory containing `index.php`).
2. Confirm `inc/plugins/pluginlibrary.php` exists. If another PluginLibrary-based plugin is already installed, keep that copy.
3. In **Admin CP → Configuration → Plugins**, install and activate **Quick Thread**.
4. Open **Configuration → Settings → Quick Thread Settings** to configure the plugin.
5. Visit a forum where you can post; the quick-thread row appears above the thread list.

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| `quickthread_enabled` | `1` | Master switch for the inline form. |
| `quickthread_showchooser` | `1` | Show the index button and chooser for logged-in users. |
| `quickthread_forums` | empty | Comma-separated forum IDs allowed to show the inline form; empty means all forums. |
| `quickthread_ajax_only` | `0` | Use the AJAX submission path instead of normal form submission. |
| `quickthread_animate` | `1` | Enable expand/collapse animation. |

## Known limitations

The normal MyBB submission path is the more complete option. The README documents several limitations in the AJAX path and forum chooser, including incomplete handling of guest captcha, attachments, post/moderator options, core new-thread hooks, and some permission/settings gates. Review and test these behaviors in your own forum configuration before enabling AJAX Only Mode.

## License

MIT — see [LICENSE](LICENSE). The bundled `inc/plugins/pluginlibrary.php` is a separate GPL-3.0 work and retains its own license header.
