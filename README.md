# Markup Metadata

ProcessWire markup module for rendering meta tags in HTML document head section. Note that this module is not full-blown SEO solution, but rather a simple tool for rendering meta tags based on module configuration. Adding custom meta tags is also supported.

## Built-in meta tags

The following meta tags are supported out-of-the-box:

- Document title consisting of page title and site name
- Character set
- Canonical
- Viewport
- Description
- Keywords
- Hreflang tags
- Open Graph
  - og:title
  - og:site_name
  - og:type
  - og:url
  - og:description
  - og:image
  - og:image:width
  - og:image:height
  - og:image:alt
- Twitter meta tags
  - twitter:card
  - twitter:site
  - twitter:creator
  - twitter:title
  - twitter:description
  - twitter:image
  - twitter:image:alt
- Facebook meta tags
  - fb:app_id

## Installation

### Install using Composer

```console
composer require fokke/markup-metadata
```

### Manual installation

Extract module files to `site/modules/MarkupMetadata` directory.

## Usage

```php
// Initialize module instance
$metadata = $modules->get('MarkupMetadata');

// Optionally set your custom meta tags, or overwrite module configuration before rendering...

// Render metadata
echo $metadata->render();
```

## Public methods

### setMeta($tag, $attrs, $content)

Set custom meta tag.

- `string $tag` HTML tag name to use
- `array|null $attrs` Optional array of HTML tag attributes in the following format: `'name' => 'value'`
- `string|null $content` Optional inner content for the element. Most likely this will be used only for `<title>` tag.

```php
$metadata->setMeta('meta', [
  'name' => 'author',
  'content' => 'Jerry Cotton',
]);
// <meta name="author" content="Jerry Cotton">
```

### render()

Render all meta tags

```php
$metadata->render();
```

## Configuration

After you have installed the module, check module configuration page for available options. If you wish, all these options can be set or overwritten in code like this:

```php
// Add this line before rendering
$metadata->site_name = 'My custom site name';

// Set multiple properties
$metadata->setArray([
  'page_title' => 'My custom title',
  'site_name' => 'My custom site name',
  'description' => 'My custom description',
]);
```

### page_url

Type: `string`

This URL will be used in `canonical` and `og:url` meta tags. If unset, page URL will be dynamically built of [base_url](#baseurl), current page URL, and URL segments (if defined).

### base_url

Type: `string`, Default: `'https://domain.com'`

Used as a base for building the current page URL.

### charset

Type: `string`, Default: `'utf-8'`

Used in `charset` meta tag.

### viewport

Type: `string`, Default: `'width=device-width, initial-scale=1.0'`

Used in `viewport` meta tag.

### keywords

Type: `string`

Used in `keywords` meta tag. If unset, keywords of the current page will be used. See [keywords_selector](#keywordsselector).

### keywords_selector

Type: `string`, Default: `'keywords'`

This selector will be used to get current page keywords using `$page->get()` method.

### document_title

Type: `string`

By default document title will be built of [page_title](#pagetitle), [document_title_separator](#documenttitleseparator) and [site_name](#sitename). You can overwrite this property if you want a fully customized document title.

### page_title

Type: `string`

Value will be used in `title`, `og:title` and `twitter:title` meta tags. If unset, title of the current page will be used. See [page_title_selector](#pagetitleselector).

### page_title_selector

Type: `string`

This selector will be used to get current page title using `$page->get()` method.

### document_title_separator

Type: `string`, Default: `'-'`

Value will be used to separate page title and site name in document title.

### site_name

Type: `string`, Default: `'Site name'`

Value will be added to the document title after page title. It will also be used in `og:site_name` meta tag.

### description

Type: `string`

Used in `description`, `og:description`, and `twitter:description` meta tags. If unset, description of the current page will be used. See [description_selector](#descriptionselector).

### description_selector

Type: `string`, Default: `'summary'`

This selector will be used to get current page description using `$page->get()` method.

### description_max_length

Type: `integer`, Default: `'160'`

Description will be truncated to the specified number of characters.

### description_truncate_mode

Type: `string`, Default: `'word'`

Select truncate mode to use with [`$sanitizer->truncate()`](https://processwire.com/api/ref/sanitizer/truncate/) method.

### image

Type: `\ProcessWire\Pageimage`

Used in `og:image` and `twitter:image` meta tags. By default the module will attempt to get image from the current page by using [image_selector](#imageselector). This image will be resized to the dimensions defined by [image_width](#imagewidth) and [image_height](#imageheight) properties.

### image_selector

Type: `string`, default: `'image'`

This selector will be used to get current page image using `$page->get()` method.

### image_width

Type: `integer`, Default: `1200`

Image will be resized to specified width.

### image_height

Type: `integer`, Default: `630`

Image will be resized to specified height.

### image_alt_field

Type: `string`, Default: `alt`

The value of this field will be used as an alternative text of the image. Enable custom fields for your image field and specify the field name.

### image_inherit

Type: `boolean`, Default: `false`

If the image cannot be found from the current page, the module will try to find the image from the nearest parent page (including home page).

### image_fallback_page

Type: `\ProcessWire\Page`, Default: `null`

If the image cannot be found from the current page (and possibly enabled inheritance fails), the module will try to find the image from the given page. Use this to define default image for all pages. The selector defined above will be used to find the image.

### render_hreflang

Type: `boolean`, Default: `false`

Toggle rendering of hreflang tags on/off. To enable, set value to `true`. In order to render hreflang tags, the following requirements must be met:

1. Your site has at least two languages set up
2. LanguageSupportPageNames module is installed
3. Field defined in property [hreflang_code_field](#hreflangcodefield) exists and your language template includes that field.
4. Language code field is populated in every language page. If the language code field is empty, the hreflang tag will not be rendered for that language.

### hreflang_code_field

Type: `string`, Default: `'languageCode'`

This field name will be used define language code for every language page.

### render_og

Type: `boolean`, Default: `true`

Toggle rendering of Open Graph tags on/off. To disable, set value to `false`.

### og_type

Type: `string`, Default: `'website'`

Open Graph type of the page/resource. Used in `og:type` meta tag.

### render_twitter

Type: `boolean`, Default: `false`

Toggle rendering of Twitter tags on/off. To enable, set value to `true`.

### twitter_card

Type: `string`, Default: `'summary_large_image'`

Twitter card type, which can be one of `summary`, `summary_large_image`, `app`, or `player`. Used in `twitter:card` meta tag.

### twitter_site

Type: `string`, Default: `null`

Twitter user name. Used in `twitter:site` meta tag.

### twitter_creator

Type: `string`, Default: `null`

Twitter user name. Used in `twitter:creator` meta tag.

### render_facebook

Type: `boolean`, Default: `false`

Toggle rendering of Facebook tags on/off. To enable, set value to `true`.

### facebook_app_id

Type: `string`, Default: `null`

Facebook application ID. Used in `fb:app_id` meta tag.
