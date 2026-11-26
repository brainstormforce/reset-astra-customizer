# Codebase Map — Astra Customizer Reset

## Directory Structure

```
reset-astra-customizer/
├── class-astra-theme-customizer-reset.php  # Plugin entry point: reset flag, AJAX reset, script data
├── assets/
│   ├── css/customizer-reset.css           # Reset All button and menu styles (LTR + RTL)
│   └── js/customizer-reset.js             # Reset All menu: Reset in Preview, Reset and Publish Now, backup link
├── languages/                             # Translation template (.pot)
├── .distignore                            # WordPress.org distribution exclusions
├── .gitignore                             # Git exclusions
├── Gruntfile.js                           # Grunt build configuration
├── package.json                           # Node.js dependencies
├── CLAUDE.md                              # Claude Code project context
└── .claude/settings.json                  # Claude Code tool permissions
```
