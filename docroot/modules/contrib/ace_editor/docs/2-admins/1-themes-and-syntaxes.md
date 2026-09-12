# Themes and Syntaxes

The list of selectable themes and language modes comes from two places:

1. The `theme_list` and `syntax_list` maps in `ace_editor.settings`, which drive
   the **Theme** and **Syntax** select lists in every settings form.
2. The actual `theme-*.js` and `mode-*.js` files present in your Ace library,
   which are auto-discovered as Drupal libraries
   (`ace_editor/theme.<name>` and `ace_editor/mode.<name>`) by
   `hook_library_info_build()`.

At render time the module checks whether the chosen `theme.<name>` /
`mode.<name>` library exists; if not, it falls back to the default theme/syntax
from configuration. So selecting a theme that isn't present in your library
build degrades gracefully rather than breaking the editor.

## Themes (examples)

Cobalt, Ambiance, Chaos, Chrome, Clouds, Crimson Editor, Dawn, Dreamweaver,
Eclipse, GitHub, Idle Fingers, Katzenmilch, Kuroir, Merbivore, Mono Industrial,
Monokai, Pastel On Dark, Solarized Dark, Solarized Light, SQL Server, Terminal,
TextMate, Tomorrow Night (and variants), Twilight, Vibrant Ink, Xcode, …

## Syntaxes (examples)

HTML, PHP, JavaScript, JSON, CSS, SCSS, LESS, YAML, Twig, Markdown, SQL, Bash,
C/C++, C#, Java, Python, Ruby, Rust, Go, TypeScript, JSX/TSX, XML, Dockerfile,
Diff, INI, Properties, Plain Text, … and many more.

The complete shipped lists are defined in
`config/install/ace_editor.settings.yml`. To expose additional themes/modes,
ensure the corresponding `theme-*.js` / `mode-*.js` files exist in your Ace
library and that the name appears in the relevant list.
