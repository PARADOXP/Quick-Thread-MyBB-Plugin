# Quick Thread — MyBB Plugin

Inline, expanding thread-creation form for forum display pages, plus a forum chooser overlay on the board index. Inspired by XenForo's quick-thread box.

The form starts as a single subject input in the thread list. Focusing it expands a full editor with MyCode buttons, smilies, post options, a poll tab, and a submit button. Threads can be submitted either through MyBB's normal `newthread.php` flow or through the plugin's own `xmlhttp.php` handler, depending on the **AJAX Only Mode** setting.

## Features

- **Inline form in the thread list** — injected as an extra row in `forumdisplay_threadlist`, directly under the inline-moderation controls.
- **Expand on focus** — the collapsed subject field expands into the full form; the animation can be disabled.
- **Full MyBB editor** — calls `build_mycode_inserter()` so the textarea gets SCEditor, the MyCode toolbar, and clickable smilies, subject to the forum's `allowmycode` / `allowsmilies` and the user's `showcodebuttons` setting.
- **Message and Poll tabs** — the poll tab exposes `postpoll` and `numpolloptions`, matching the core new-thread poll flow (the thread is created first, then the user is sent to `polls.php` to fill in the options).
- **Prefix selection** — `build_prefix_select()` is rendered in the collapsed row when the forum has prefixes.
- **Post options** — signature, thread subscription, and "disable smilies" checkboxes.
- **Guest handling** — guests who can view a forum see the form with a login/register notice.
- **Captcha markup for guests** — built with MyBB's `captcha` class when `captchaimage` is enabled.
- **Two submission modes** — normal POST to `newthread.php`, or AJAX POST to `xmlhttp.php?action=quickthread_submit`.
- **AJAX response handling** — validation errors are shown in the form; success redirects to the new thread; a poll response redirects to `polls.php`.
- **Forum chooser on the index** — logged-in users get a "New Thread" button that opens an overlay listing forums they can post in.
- **Permission gate** — master switch, forum allow-list, `canview`, `canpostthreads`, `suspendposting`, forum type, forum open state, and redirect forums.
- **PluginLibrary integration** — settings from `settings.json`, eight templates, and the stylesheet are registered through PluginLibrary.

## Requirements

| Requirement | Version / note |
| --- | --- |
| MyBB | 1.8.x (`compatibility` is `18*`) |
| PHP | 7.4+; uses `??` and typed assumptions that work on PHP 8.x |
| PluginLibrary | **Required** — bundled at `inc/plugins/pluginlibrary.php` |
| SCEditor | Stock MyBB assets under `jscripts/sceditor/` |
| jQuery | Stock MyBB front-end jQuery |

The plugin has no database tables, no admin module, no core file edits, and no file-permission requirements. It stores its state in MyBB settings and templates.

## Installation

1. Extract the repository into your MyBB root (the directory containing `index.php`).
2. Confirm `inc/plugins/pluginlibrary.php` exists. If you already run another PluginLibrary-based plugin, keep your existing copy.
3. **Admin CP → Configuration → Plugins** → **Quick Thread** → **Install**, then **Activate**.
4. **Configuration → Settings → Quick Thread Settings** to configure the five settings.
5. Open a forum you can post in. The quick-thread row appears above the thread list.

Activation performs two `find_replace_templatesets()` edits:

- `forumdisplay_threadlist`: inserts an `inline-row` containing `{$quickthread}` after `{$inlinemodcol}</tr>`.
- `index`: inserts `{$quickthread_button}{$quickthread_chooser}` immediately after `{$header}`.

Deactivation reverses both edits. Uninstall removes the settings, templates, and cached stylesheet.

## Repository layout

```
inc/plugins/quickthread.php                                  # bootstrap, hooks, install, AJAX handlers, helpers
inc/plugins/Quickthread/settings.json                        # 5 settings
inc/plugins/Quickthread/Templates/quickthread.html           # main inline form
inc/plugins/Quickthread/Templates/quickthread_form_fields.html# alternate form body (AJAX load path)
inc/plugins/Quickthread/Templates/quickthread_chooser.html   # index overlay
inc/plugins/Quickthread/Templates/quickthread_chooser_category.html
inc/plugins/Quickthread/Templates/quickthread_chooser_forum.html
inc/plugins/Quickthread/Templates/quickthread_newthread_button.html
inc/plugins/Quickthread/Templates/quickthread_errors.html
inc/plugins/Quickthread/Templates/quickthread_error_item.html
inc/plugins/Quickthread/Assets/quickthread.css               # form, overlay, animation styling
inc/plugins/Quickthread/Assets/quickthread.js                # expand/collapse, tabs, AJAX submit, chooser
inc/plugins/pluginlibrary.php                                # bundled PluginLibrary
inc/languages/english/quickthread.lang.php                   # front-end phrases
```

There is no Admin CP language file; setting titles and descriptions live in `settings.json` and are English-only.

## Settings (5)

| Setting | Default | Description |
| --- | --- | --- |
| `quickthread_enabled` | `1` | Master switch for the inline form. |
| `quickthread_showchooser` | `1` | Show the index "New Thread" button and forum chooser for logged-in users. |
| `quickthread_forums` | empty | Comma-separated forum IDs where the inline form is allowed. Empty means all forums. |
| `quickthread_ajax_only` | `0` | Submit through `xmlhttp.php` instead of `newthread.php`. The setting only changes the client-side submit path; see the notes below. |
| `quickthread_animate` | `1` | Enable the expand/collapse animation. |

Settings are registered by `quickthread_add_settings()` through `$PL->settings('quickthread', ...)`, with the group name and the `quickthread_` prefix stripped from each key before handing them to PluginLibrary.

## Hooks

| Hook | Callback | Purpose |
| --- | --- | --- |
| `global_start` | `quickthread_global_start()` | Adds the eight plugin templates and `codebuttons` to `$templatelist`. |
| `forumdisplay_start` | `quickthread_forumdisplay_start()` | Loads CSS/JS/SCEditor when the master switch is on. |
| `forumdisplay_threadlist` | `quickthread_forumdisplay()` | Builds `$quickthread` for the thread-list row. |
| `index_start` | `quickthread_index_start()` | Loads CSS/JS when the chooser is enabled. |
| `index_end` | `quickthread_index()` | Builds `$quickthread_button` and `$quickthread_chooser`. |
| `xmlhttp` | `quickthread_ajax_handler()` | Handles `action=quickthread_load_form` and `action=quickthread_submit`. |

## How it works

### Inline form

`quickthread_forumdisplay()` calls `quickthread_can_use($fid)`. Return values are `true` (logged in and allowed), `false` (hidden), or `'guest'` (form shown with a login notice). When allowed, it builds the prefix selector, avatar, editor, post options, poll controls, captcha, and evaluates `quickthread_quickthread`.

The form posts to `newthread.php?fid={fid}&processed=1` with `action=do_newthread` and a post key, so the non-AJAX path is an ordinary core new-thread submission. When **AJAX Only Mode** is on, `quickthread.js` intercepts the submit event, serialises the form with `FormData`, takes the message from `MyBBEditor.val()` if SCEditor is active, and posts to `xmlhttp.php?action=quickthread_submit&fid={fid}`.

### AJAX handler

`quickthread_ajax_handler()` serves two actions:

- `quickthread_load_form` validates the forum and permissions, builds the editor with `build_mycode_inserter()`, evaluates `quickthread_quickthread_form_fields`, and returns the HTML as JSON.
- `quickthread_submit` verifies the post key, checks `canpostthreads`, validates subject/message, runs `PostDataHandler("insert")` with `action = "thread"`, calls `validate_thread()` and `insert_thread()`, and returns `{tid, pid, link, message}`. If `postpoll=1` and the user may post polls, it returns a `polls.php?action=newpoll` redirect instead.

### Index chooser

`quickthread_index()` returns early for guests and when `quickthread_showchooser` is off. Otherwise `quickthread_build_forum_list()` queries active forums, keeps those with `canview` and `canpostthreads`, builds a two-level tree, and renders category/forum templates. `quickthread.js` opens the overlay with `QuickThreadChooser.open()` and closes it on overlay click, the close button, or Escape.

## Notes and known limitations

### Forum chooser is usually empty

This is the largest functional defect. `quickthread_build_forum_list()` applies `canview == 1 && canpostthreads == 1` to **every** forum row, including categories. On a stock board categories grant `canview` but not `canpostthreads`, so no category is added to `$forum_tree`. The render loop only outputs categories (`if($data['is_category'])`), so the overlay has nothing to show. Top-level forums with `pid = 0` are also collected but never rendered. In practice the "New Thread" button often opens an empty overlay. A board where categories were deliberately given `canpostthreads` would render, but that is not a normal configuration.

### Index ignores the master switch

`quickthread_index()` checks only `quickthread_showchooser`, not `quickthread_enabled`. With the plugin disabled but the chooser setting left on, the button and overlay markup are still inserted. `quickthread_index_start()` does not load the JavaScript when the plugin is disabled, so the button calls an undefined `QuickThreadChooser` and does nothing.

### The AJAX endpoint ignores the plugin's own gates

`quickthread_submit` checks the post key and `canpostthreads`, but never `quickthread_enabled`, `quickthread_forums`, or `quickthread_ajax_only`. A request can create a thread through the AJAX handler in a forum excluded by the allow-list, or while the master switch is off, as long as the user can post threads there.

### Guest captcha is never validated

The inline form builds a real captcha via `new captcha(false, "post_captcha")`, and the AJAX-load path emits a hardcoded `<img src="captcha.php?action=new">` plus an `imagestring` field. `quickthread_submit` never validates either one. In AJAX mode a guest with posting permission can create threads without solving a captcha. The hardcoded image URL is also not MyBB's normal registration/post captcha flow, so that image may not render.

### AJAX mode ignores post options and moderator options

The form renders signature, subscription, and "disable smilies" checkboxes, and moderator close/stick checkboxes, but `quickthread_submit` builds `$new_thread` without `options` or `modoptions`. `PostDataHandler` therefore applies its defaults: the signature and smilie choices are discarded, no subscription is created, and the thread is never closed or stuck. In the non-AJAX path these fields are handled by core `newthread.php` as usual.

### Custom thread fields are dead code

`quickthread_build_custom_fields()` is never called and `$custom_fields` is always empty. The function also queries a `threadfields` table that does not exist in stock MyBB 1.8 (thread fields are not part of the 1.8 schema). Its `forums LIKE '%,{fid},%'` match and its checkbox `explode('\n', ...)` are both broken for real data. Forums that rely on a thread-field plugin will not receive those fields from this form.

### Attachments are not supported

The form is `multipart/form-data` and carries a generated `posthash`, but `$attachment_field` is always empty and the submit handler never processes uploads. Inline attachments cannot be added; users must use "More Options" (which redirects to `newthread.php` with the subject and message pre-filled).

### AJAX submission skips the new-thread hook sequence

The AJAX handler inserts through the post data handler directly. Core `newthread.php` runs `newthread_do_newthread_start`, `newthread_do_newthread_end`, and related page hooks; those do not run in AJAX mode. Plugins that hook the normal new-thread flow (notifications, cross-posting, custom validation, redirects) will not see AJAX-created threads. The `datahandler_post_*` hooks still run.

### Moderation notice is not reproduced

Core `newthread.php` detects an unapproved thread and redirects to the forum with a moderation message. The AJAX handler always returns the thread link, so the client redirects straight to the thread even when it is held for moderation.

### "AJAX Only Mode" is only a client-side switch

The setting only changes what `quickthread.js` does with the submit event. The normal `newthread.php` form remains reachable, and the AJAX endpoint accepts submissions regardless of the setting. It does not make AJAX the only possible path.

### Templates are never refreshed

`quickthread_add_templates()` returns early if any master template named `quickthread` exists (`sid = -2`). Activating again, or upgrading the plugin files, does not update changed `.html` files and does not add new template keys. Existing installations keep stale templates until the templates are deleted manually or the plugin is uninstalled and reinstalled.

### Duplicate assets and hardcoded tags

`quickthread_add_resources()` appends literal `<link>` and `<script>` tags to `$headerinclude` with `?v=2.0`. On `forumdisplay.php` it also injects the SCEditor stylesheet, `jquery.sceditor.bbcode.min.js`, `bbcodes_sceditor.js`, and `undo.js`, even though `build_mycode_inserter()` emits the same assets through the `codebuttons` template. The result is duplicate SCEditor CSS/JS on forum display. Separately, `quickthread_add_stylesheet()` registers `quickthread.css` with PluginLibrary **and** `quickthread_add_resources()` links the raw file, so the plugin CSS loads twice.

The tags are not registered templates, so they cannot be edited from the ACP theme editor. `quickthread_add_resources()` also runs on every forum display when the master switch is on, including forums excluded by `quickthread_forums` and users who cannot post.

### Version strings disagree

`quickthread_info()` reports `2.1.0`; the PHP file header says `@version 1.0`; `quickthread.js` says `@version 2.0` and the asset query strings use `v=2.0`.

### Dead code and unused templates

- `quickthread_load_form` / `loadForm()` are never triggered by the main template, which renders the form inline. Only the AJAX load path uses `quickthread_form_fields.html`.
- `insertThread()` is never called; the success path redirects instead of inserting a row.
- `quickthread_get_theme()`, `quickthread_build_custom_fields()`, `quickthread_build_mycode_inserter()`, and `quickthread_get_post_icons()` have no callers.
- `quickthread_errors.html` and `quickthread_error_item.html` are registered but `showErrors()` builds error markup as a string instead.
- `quickthread_build_mycode_inserter()` embeds inline `onclick` handlers and a `<script>` block; it is unused.
- `quickthread_form_fields.html` duplicates most of `quickthread.html`, so the two can drift.

### Error strings are inserted as HTML

`QuickThread.showErrors()` uses `insertAdjacentHTML('afterbegin', ...)` with raw server error strings. Data-handler errors are normally language phrases, but any error text that includes user-controlled content would be interpreted as HTML. The success path uses `textContent`, so it is safe.

### Smaller issues

- `quickthread_can_use()` treats `quickthread_forums` as a list of string IDs and compares them with `in_array($fid, ...)` without normalising types; it works because PHP coerces numeric strings, but the setting is unvalidated.
- Guest avatar fallback hardcodes `images/default_avatar.png` instead of using the theme's image directory.
- `$mybb->settings['quickthread_enabled']` is read without a null-coalescing guard in `quickthread_index_start()`; a missing setting produces an "Undefined array key" warning on PHP 8.
- The post key is verified on the AJAX submit path, so the handler is CSRF-protected. The permission/settings bypasses above are separate from CSRF.
- There is no Admin CP language file; only `inc/languages/english/quickthread.lang.php` ships, and it is loaded on the front end with `$lang->load('quickthread')`.

## Author

**PARADOX** — [MyBB Community profile](https://community.mybb.com/user-106685.html)

- Website: https://mybb.epizy.com
- Version: 2.1.0

The `_info()` metadata uses `PARADOX` and user ID `106685`. The source file header also declares `@license MIT License`; that is the license used for this repository. PluginLibrary remains under its own GPL-3.0 license.

## License

MIT — see [LICENSE](LICENSE). The bundled `inc/plugins/pluginlibrary.php` is a separate GPL-3.0 work and keeps its own header.
