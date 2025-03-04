![Screenshot of Get a Newsletter WordPress Plugin](assets/admin/img/banner-1544x500.png)

# Get a Newsletter WordPress Plugin

Turn visitors into subscribers. Eliminate manual entry of subscribers with signup forms that sync directly with your Get a Newsletter account.

## Features

- Create and manage newsletter forms directly in WordPress
- Add forms using the block editor (Gutenberg)
- Customize form appearance with colors and styles
- Add forms using widgets in sidebars or footer
- Add forms using shortcodes in posts or pages
- Support for both embedded and popup forms
- Multiple forms can be used on the same page
- Available in English and Swedish

## Documentation

For the official WordPress plugin documentation, see [readme.txt](./readme.txt).

## Development

### Requirements

- PHP 7.2 or higher
- WordPress 5.2 or higher
- Node.js and npm for building block editor components
- Composer for PHP dependencies

### Local Development Setup

1. Clone the repository

```bash
git clone git@github.com:getanewsletter/wp-get-a-newsletter.git
cd wp-get-a-newsletter
```

2. Install JavaScript dependencies and build block editor components

```bash
cd blocks
npm install
npm run build
```

3. For development, start the dev server from while standing in the `blocks`-folder:

```bash
npm run start
```

### Plugin Structure

```
getanewsletter/
├── assets/               # CSS, JS, and images
├── blocks/              # Gutenberg block components
│   ├── src/             # Block source code
│   └── build/           # Compiled block files
├── languages/           # Translation files
├── GAPI.class.php      # API integration class
├── getanewsletter.php  # Main plugin file
├── readme.md           # This file (for GitHub)
├── readme.txt          # WordPress.org plugin repository file
└── subscribe.php       # Form submission handling
```

### Translation

The plugin uses WordPress' translation system and supports translations for both PHP and JavaScript code. We use POEdit for managing translations.

#### Prerequisites

1. Install [WP-CLI](https://wp-cli.org/) for generating translation files
2. Install [POEdit](https://poedit.net/) for managing translations

#### Translation Process

1. **Generate/Update POT file**

   ```bash
   wp i18n make-pot . languages/getanewsletter.pot
   ```

   This creates/updates the template file containing all translatable strings from both PHP and JavaScript.

2. **Edit Translations using POEdit**

   - Open POEdit
   - Go to File > Open
   - Navigate to the `languages/getanewsletter-sv_SE.po` file and open it
   - Go to Translation > Update from POT file...
     - This will update the list of translated strings with the new ones
   - Translate all new strings
   - Save the file (this automatically creates the .mo file)

3. **Generate JSON files for JavaScript translations**
   ```bash
   wp i18n make-json languages/ --no-purge
   ```
   This creates JSON files required for JavaScript translations.

#### File Structure

After completing the translation process, you should have these files in your `languages` directory:

```
languages/
├── getanewsletter.pot              # Template file
├── getanewsletter-sv_SE.po        # Swedish translation source
├── getanewsletter-sv_SE.mo        # Swedish translation compiled
└── getanewsletter-sv_SE-[hash].json # Swedish translation for JavaScript
```

#### Testing Translations

1. Set your WordPress site language to the translated locale
2. Check both admin and frontend areas
3. Verify translations in:
   - PHP strings (forms, admin pages)
   - JavaScript strings (block editor, dynamic content)
   - Error messages and notifications

#### Adding New Translations

1. Always start with the latest .pot file
2. Create new .po file with correct locale code
3. Translate all strings
4. Save to generate .mo file
5. Generate JSON files
6. Test thoroughly
7. Commit all translation files (.po, .mo, and .json)

### Testing

Before submitting a pull request, please ensure:

1. Your code follows WordPress coding standards
2. All JavaScript is properly built (`npm run build`)
3. No PHP errors or warnings are introduced
4. Translations are up to date

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Links

- [Get a Newsletter Website](https://www.getanewsletter.com)
- [Support Documentation](https://support.getanewsletter.com)
- [WordPress.org Plugin Page](https://wordpress.org/plugins/getanewsletter/)
