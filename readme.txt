=== AutoloadForge ===
Contributors: gunjanjaswal
Tags: performance, autoload, options, database, optimization
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See what is bloating your autoloaded options, trace each one to a likely plugin, and switch autoload off reversibly.

== Description ==

Every WordPress page load pulls in your autoloaded options in one go. Over time plugins pile data into that bundle, and a lot of it does not need to load on every request. When it grows into the megabytes, every page pays for it.

AutoloadForge gives you a plain Tools screen to see the problem and act on it:

* A running total of how much data autoloads, and how many options make it up.
* The biggest autoloaded options, largest first.
* A best-effort guess at which plugin each option belongs to, so you know what you are looking at.
* A one-click switch to stop an option autoloading, and a list of everything you have switched off so you can turn any of it back on.

It does not delete anything. Turning autoload off leaves the option in place and fully intact, it just stops loading on every request. If something turns out to need it, restore it in one click.

No settings to configure, no external calls, no tracking. Open the page, read it, decide.

== Installation ==

1. Upload the `autoloadforge` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Go to Tools then AutoloadForge.

== Frequently Asked Questions ==

= Is it safe to stop an option autoloading? =

Yes. The option and its value stay in the database untouched. All that changes is whether it loads automatically on every page. If a plugin needs it later, WordPress still reads it on demand, and you can restore autoloading from the same screen.

= Could this break my site? =

Switching autoload off does not remove data, so it will not break anything. In rare cases an option that genuinely benefits from autoloading might add a small query when it is needed. If you ever suspect that, the restore button puts it back.

= How is the "likely source" worked out? =

By matching the option name against your active plugins. It is a helpful hint, not a guarantee, since plugins name their options however they like. Unmatched options are labelled as core or unknown.

= What is a good total? =

Keeping autoloaded data under roughly 800KB to 1MB is a sensible target. The status card turns amber and then red as you cross those marks.

== Screenshots ==

1. The AutoloadForge screen: totals, the largest autoloaded options, and the switch to stop autoloading.

== Changelog ==

= 1.0.0 =
* First release: autoload overview, per-option source guess, and a reversible switch to stop and restore autoloading.
