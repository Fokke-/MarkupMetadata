# Markup Metadata

ProcessWire 3.x markup module for rendering meta tags in HTML document head section. Note that this module is not full-blown SEO solution, but rather a simple tool for rendering meta tags based on module configuration. Adding custom meta tags is also supported.

## Built-in  meta tags

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
- Twitter meta tags
  - twitter:card
  - twitter:site
  - twitter:creator
  - twitter:title
  - twitter:description
  - twitter:image
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
$metadata->siteName = 'My site';
```

### pageTitle

Type: `string`

Value will be used in `title`, `og:title` and `twitter:title` meta tags. If unset, title of the current page will be used. See [pageTitleSelector](#pagetitleselector).

### pageTitleSelector

Type: `string`

This selector will be used to get current page title using `$page->get()` method.

### siteName

Type: `string`, Default: `'Site name'`

Value will be added to the document title after page title. It will also be used in `og:site_name` meta tag.

### documentTitleSeparator

Type: `string`, Default: `'-'`

Value will be used to separate page title and site name in document title.

### documentTitle

Type: `string`

By default, document title will be built of [pageTitle](#pagetitle), [documentTitleSeparator](#documenttitleseparator) and [siteName](#sitename). You can overwrite this property if you want a fully customized document title.

### baseUrl

Type: `string`, Default: `'https://domain.com'`

Used as a base for building the current page URL.

### pageUrl

Type: `string`

This URL will be used in `canonical` and `og:url` meta tags. If unset, page URL will be dynamically built of [baseUrl](#baseurl), current page URL, and URL segments (if defined).

### description

Type: `string`

Used in `description`, `og:description`, and `twitter:description` meta tags. If unset, description of the current page will be used. See [descriptionSelector](#descriptionselector).

### descriptionSelector

Type: `string`, Default: `'summary'`

This selector will be used to get current page description using `$page->get()` method.

### keywords

Type: `string`

Used in `keywords` meta tag. If unset, keywords of the current page will be used. See [keywordsSelector](#keywordsselector).

### keywordsSelector

Type: `string`, Default: `'keywords'`

This selector will be used to get current page keywords using `$page->get()` method.

### charset

Type: `string`, Default: `'utf-8'`

Used in `charset` meta tag.

### viewport

Type: `string`, Default: `'width=device-width, initial-scale=1.0'`

Used in `viewport` meta tag.

### image

Type: `\ProcessWire\Pageimage`

Used for `og:image` and `twitter:site` meta tags. By default, the module will attempt to get image from the current page by using [imageSelector](#imageselector). This image will be resized to the dimensions defined by [imageWidth](#imagewidth) and [imageHeight](#imageheight) properties.

**Note that if you set image manually, it will not be resized automatically**.

### imageSelector

Type: `string`, default: `'image'`

This selector will be used to get current page image using `$page->get()` method.

### imageWidth

Type: `integer`, Default: `1200`

If image was set automatically using [imageSelector](#imageselector), this value will be used as width when resizing that image.

### imageHeight

Type: `integer`, Default: `630`

If image was set automatically using [imageSelector](#imageselector), this value will be used as height when resizing that image.

### render_hreflang

Type: `boolean`, Default: `false`

Toggle rendering of hreflang tags on/off. To enable, set value to `true`. In order to render hreflang tags, the following requirements must be met:

1. Your site has at least two languages set up
2. LanguageSupportPageNames module is installed
3. Field defined in property [hreflangCodeField](#hreflangcodefield) exists and your language template includes that field.
4. Language code field is populated in every language page. If the language code field is empty, the hreflang tag will not be rendered for that language.

### hreflangCodeField

Type: `string`, Default: `'languageCode'`

This field name will be used define language code for every language page.

### render_og

Type: `boolean`, Default: `true`

Toggle rendering of Open Graph tags on/off. To disable, set value to `false`.

### og_type

Type: `string`, Default: `'website'`

The type of the resource.

### render_twitter

Type: `boolean`, Default: `false`

Toggle rendering of Twitter tags on/off. To enable, set value to `true`.

### twitterName

Type: `string`, Default: `null`

Twitter user name. Used in `twitter:site` and `twitter:creator` meta tags.

### twitterCard

Type: `string`, Default: `'summary_large_image'`

Twitter card type. Used in `twitter:card` meta tag.

### render_facebook

Type: `boolean`, Default: `false`

Toggle rendering of Facebook tags on/off. To enable, set value to `true`.

### facebookAppId

Type: `string`, Default: `null`

Facebook application ID. Used in `fb:app_id` meta tag.
