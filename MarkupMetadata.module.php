<?php namespace ProcessWire;

class MarkupMetadata extends WireData implements Module, ConfigurableModule {

  /**
   * Array for meta tags
   *
   * @var array
   */
  private $tags = [];

  /**
   * Image for the page being rendered
   *
   * @var \ProcessWire\Pageimage|null
   */
  public $image = null;

  /**
   * Alternative text for image
   *
   * @var string|null
   */
  public $image_alt = null;

  /**
   * Module info
   *
   * @return Array
   */
  public static function getModuleInfo() : array {
    return [
      'title' => 'Markup Metadata',
      'version' => 121,
      'summary' => 'Set and render meta tags for head section.',
      'author' => 'Ville Fokke Saarivaara',
      'singular' => true,
      'autoload' => false,
      'icon' => 'hashtag',
      'requires' => [
        'ProcessWire>=3.0.142',
      ],
    ];
  }

  /**
   * Module default configuration
   *
   * @return Array
   */
  public static function getDefaultData() : array {
    return [
      'base_url' => 'https://domain.com',
      'charset' => 'utf-8',
      'viewport' => 'width=device-width, initial-scale=1.0',
      'keywords_selector' => 'keywords',
      'page_title_selector' => 'title',
      'document_title_separator' => '-',
      'site_name' => 'Site name',
      'description_selector' => 'summary',
      'description_max_length' => 160,
      'description_truncate_mode' => 'word',
      'image_selector' => 'image',
      'image_width' => 1200,
      'image_height' => 630,
      'image_alt_field' => 'alt',
      'image_inherit' => 0,
      'image_fallback_page' => null,
      'render_hreflang' => 0,
      'hreflang_code_field' => 'languageCode',
      'render_og' => 1,
      'og_type' => 'website',
      'render_twitter' => 1,
      'twitter_card' => 'summary_large_image',
      'twitter_site' => null,
      'twitter_creator' => null,
      'render_facebook' => 0,
      'facebook_app_id' => null,
    ];
  }

  /**
   * Constructor
   */
  public function __construct() {
    // Set default configuration
    foreach(self::getDefaultData() as $key => $val) {
      $this->$key = $val;
    }
  }

  /**
   * Set a new meta tag
   *
   * @param string $key Tag name
   * @param array|null $attrs An array of HTML tag attributes in the following format: 'name' => 'value'.
   * @param string|null $content Inner content for the tag
   * @return array $tags All defined tags
   */
  public function setMeta(string $tag, ?array $attrs = [], ?string $content = null) : array {
    $this->tags[] = [
      'tag' => $tag,
      'attrs' => $attrs,
      'content' => $content,
    ];

    return $this->tags;
  }

  /**
   * Get page URL
   *
   * @param \ProcessWire\Language|null $language ProcessWire language page
   * @return string|null
   */
	private function getPageUrl (?\ProcessWire\Language $language = null) : ?string {
    if (empty($this->base_url)) return null;

    $url = implode('/', array_filter([
      rtrim($this->base_url, '/'),
      trim((!empty($language)) ? $this->page->localUrl($language) : $this->page->url, '/'),
      $this->page->template->urlSegments === 1 ? $this->input->urlSegmentStr : null,
    ]));

    // Add trailing slash if required
    if (
      ($this->page->template->urlSegments === 1 && $this->page->template->slashUrlSegments === 1 && $this->input->urlSegmentStr) ||
      ($this->page->template->slashUrls === 1 && !$this->input->urlSegmentStr)
    ) {
      $url .= '/';
    }

    return $url;
	}

  /**
   * Load meta tags based on module configuration
   *
   * @return object $this
   */
  private function load() : object {
    // Dynamic properties
    $this->page_title = $this->page_title ?? $this->page->get($this->page_title_selector);
    $this->page_url = $this->page_url ?? $this->getPageUrl();
    $this->description = $this->description ?? $this->page->get($this->description_selector);
    $this->keywords = $this->keywords ?? $this->page->get($this->keywords_selector);

    // Build document title
    if (empty($this->document_title)) {
      $this->document_title = implode(' ', array_filter([
        $this->page_title ?? null,
        (!empty($this->page_title) && !empty($this->site_name)) ? $this->document_title_separator : null,
        $this->site_name ?? null,
      ]));
    }

    // Truncate description
    $this->description = $this->sanitizer->truncate($this->description, (int) $this->description_max_length, $this->description_truncate_mode);

    // Get image
    $this->image = (function () {
      // Try to use possibly pre-defined image
      $image = $this->resolveImage($this->image);
      if (!empty($image)) return $this->resizeImage($image);

      // Try to find image from the current page
      $image = $this->findImageFromPage($this->page);
      if (!empty($image)) return $this->resizeImage($image);

      // Try to find image from the nearest parent
      if ((bool) $this->image_inherit === true && $this->page->parents->count()) {
        foreach ($this->page->parents->reverse() as $parent) {
          $image = $this->findImageFromPage($parent);
          if (!empty($image)) return $this->resizeImage($image);
        }
      }

      // Try to find image from the fallback page
      $image = $this->findImageFromPage($this->pages->findOne((int) $this->image_fallback_page));
      if (!empty($image)) return $this->resizeImage($image);

      return null;
    })();

    // Get alternative text for image
    $this->image_alt = (function () {
      if (empty($this->image) || empty($this->image_alt_field)) return null;

      return $this->image->get((string) $this->image_alt_field);
    })();

    // General tags
    if (!empty($this->document_title)) $this->setMeta('title', null, $this->document_title);
    if (!empty($this->charset)) $this->setMeta('meta', ['charset' => $this->charset]);
    if (!empty($this->page_url)) $this->setMeta('link', ['rel' => 'canonical', 'href' => $this->page_url]);
    if (!empty($this->viewport)) $this->setMeta('meta', ['name' => 'viewport', 'content' => $this->viewport]);
    if (!empty($this->description)) $this->setMeta('meta', ['name' => 'description', 'content' => $this->description]);
    if (!empty($this->keywords)) $this->setMeta('meta', ['name' => 'keywords', 'content' => $this->keywords]);

    // Opengraph tags
    if ((bool) $this->render_og === true) {
      if (!empty($this->page_title)) $this->setMeta('meta', ['property' => 'og:title', 'content' => $this->page_title]);
      if (!empty($this->site_name)) $this->setMeta('meta', ['property' => 'og:site_name', 'content' => $this->site_name]);
      if (!empty($this->og_type)) $this->setMeta('meta', ['property' => 'og:type', 'content' => $this->og_type]);
      if (!empty($this->page_url)) $this->setMeta('meta', ['property' => 'og:url', 'content' => $this->page_url]);
      if (!empty($this->description)) $this->setMeta('meta', ['property' => 'og:description', 'content' => $this->description]);

      // Opengraph image
      if (!empty($this->image)) {
        $this->setMeta('meta', ['property' => 'og:image', 'content' => $this->image->httpUrl]);
        $this->setMeta('meta', ['property' => 'og:image:width', 'content' => $this->image->width]);
        $this->setMeta('meta', ['property' => 'og:image:height', 'content' => $this->image->height]);

        if (!empty($this->image_alt)) {
          $this->setMeta('meta', ['property' => 'og:image:alt', 'content' => $this->image_alt]);
        }
      }
    }

    // Twitter tags
    if ((bool) $this->render_twitter === true) {
      if (!empty($this->twitter_card)) $this->setMeta('meta', ['name' => 'twitter:card', 'content' => $this->twitter_card]);
      if (!empty($this->twitter_site)) $this->setMeta('meta', ['name' => 'twitter:site', 'content' => $this->twitter_site]);
      if (!empty($this->twitter_creator)) $this->setMeta('meta', ['name' => 'twitter:creator', 'content' => $this->twitter_creator]);
      if (!empty($this->page_title)) $this->setMeta('meta', ['name' => 'twitter:title', 'content' => $this->page_title]);
      if (!empty($this->description)) $this->setMeta('meta', ['name' => 'twitter:description', 'content' => $this->description]);

      // Twitter image
      if (!empty($this->image)) {
        $this->setMeta('meta', ['name' => 'twitter:image', 'content' => $this->image->httpUrl]);

        if (!empty($this->image_alt)) {
          $this->setMeta('meta', ['property' => 'twitter:image:alt', 'content' => $this->image_alt]);
        }
      }
    }

    // Facebook tags
    if ((bool) $this->render_facebook === true) {
      if (!empty($this->facebook_app_id)) $this->setMeta('meta', ['property' => 'fb:app_id', 'content' => $this->facebook_app_id]);
    }

    // Hreflang links
    if (
      (bool) $this->render_hreflang === true &&
      $this->modules->isInstalled('LanguageSupportPageNames') &&
      $this->user->language->template->hasField($this->hreflang_code_field)
    ) {
      // Get all languages which have language code field defined
      $languages = $this->languages->find($this->hreflang_code_field .'!=""');

      // Set hreflang tags if we got more than one language in use
      if (count($languages) > 1) {
        foreach ($languages as $language) {
          if (!$this->page->viewable($language)) continue;

          $url = $this->getPageUrl($language);
          if (empty($url)) continue;

          $this->setMeta('link', [
            'rel' => 'alternate',
            'href' => $url,
            'hreflang' => $language->{$this->hreflang_code_field},
          ]);
        }
      }
    }

    return $this;
	}

  /**
   * Resolve image
   *
   * Validates an image object and returns it, if it's in supported format (PageImage or Pageimages).
   * If Pageimages object is passed, the first image will be returned.
   *
   * @param mixed $src Image to validate
   * @return \ProcessWire\Pageimage|null
   */
  protected function resolveImage ($src = null) : ?\ProcessWire\Pageimage {
    if (
      empty($src) ||
      (!$src instanceof \ProcessWire\Pageimage && !$src instanceof \ProcessWire\Pageimages) ||
      ($src instanceof \ProcessWire\Pageimages && !$src->count())
    ) {
      return null;
    }

    // If source is Pageimages object, get the first one
    if ($src instanceof \ProcessWire\Pageimages) return $src->first();

    return $src;
  }

  /**
   * Find image from the given page
   *
   * @param \Processwire\Page|null Page to search from
   * @return \ProcessWire\Pageimage|null
   */
  protected function findImageFromPage (?\ProcessWire\Page $srcPage = null) : ?\ProcessWire\Pageimage {
    if (empty($srcPage) || empty($this->image_selector)) return null;

    // Try to find image with selector
    $image = $srcPage->get($this->image_selector) ?? null;

    return $this->resolveImage($image);
  }

  /**
   * Resize image
   *
   * @param \Processwire\Pageimage|null $image Image to resize
   * @return \ProcessWire\Pageimage|null Resized image
   */
  protected function resizeImage (?\Processwire\Pageimage $image = null) : ?\ProcessWire\Pageimage {
    if (empty($image)) return null;

    // Resize image
    $width = (int) $this->image_width;
    $height = (int) $this->image_height;

    if (!empty($width) && !empty($height)) {
      return $image->size($width, $height);
    } else if (!empty($width)) {
      return $image->width($width);
    } else if (!empty($height)) {
      return $image->height($height);
    }

    return $image;
  }

  /**
   * Render meta tags
   *
   * @return string|null
   */
  public function render() : ?string {
    // Load meta tags
    $this->load();

    if (empty($this->tags)) return null;

    return array_reduce($this->tags, function ($acc, $item) {
      if (empty($item['tag'])) return $acc;

      $attrsMarkup = '';

      if (!empty($item['attrs'])) {
        foreach ($item['attrs'] as $key => $value) {
          // Discard attributes without value
          if (empty($value)) continue;

          $attrsMarkup .= ' ' . $key . '="' . $value . '"';
        }
      }

      $acc .= '<' . $item['tag'] . $attrsMarkup . '>';

      // Render inner content and closing tag
      if (!empty($item['content'])) {
        $acc .= $item['content'] . '</' . $item['tag'] . '>';
      }

      return $acc;
    }, '');
  }

  /**
   * Module configuration fields
   *
   * @param array $data Current module configuration
   * @return \ProcessWire\InputfieldWrapper
   */
  public static function getModuleConfigInputfields(array $data) : \ProcessWire\InputfieldWrapper {
    // Merge data with default values
    $data = array_merge(self::getDefaultData(), $data);
    $defaults = self::getDefaultData();

    $modules = wire('modules');

    // Truncate modes
    $truncateModes = [
      'word' => __('Word'),
      'punctuation' => __('Punctuation'),
      'sentence' => __('Sentence'),
      'block' => __('Block'),
    ];

    $inputfields = new InputfieldWrapper();

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Site settings');
    $set->icon = 'home';

      $f = $modules->get('InputfieldText');
      $f->name = 'base_url';
      $f->label = __('Base URL');
      $f->description = __('Used as a base for building the current page URL.');
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $f->icon = 'globe';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'charset';
      $f->label = __('Character set');
      $f->description = __('Used in `charset` meta tag.');
      $f->icon = 'keyboard-o';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'viewport';
      $f->label = __('Viewport');
      $f->description = __('Used in `viewport` meta tag.');
      $f->icon = 'desktop';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'keywords_selector';
      $f->label = __('Keywords selector');
      $f->description = __('This selector will be used to get current page keywords using [`$page->get()`](https://processwire.com/api/ref/page/get/) method.');
      $f->icon = 'tags';
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Document title');
    $set->icon = 'header';

      $f = $modules->get('InputfieldText');
      $f->name = 'page_title_selector';
      $f->label = __('Page title selector');
      $f->description = __('This selector will be used to get current page title using [`$page->get()`](https://processwire.com/api/ref/page/get/) method.');
      $f->icon = 'search';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'document_title_separator';
      $f->label = __('Document title separator');
      $f->description = __('Value will be used to separate page title and site name in document title.');
      $f->icon = 'ellipsis-h';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'site_name';
      $f->label = __('Site name');
      $f->description = __('Value will be added to the document title after page title. It will also be used in `og:site_name` meta tag.');
      $f->icon = 'home';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Description');
    $set->description = __('Used in `description`, `og:description`, and `twitter:description` meta tags.');
    $set->icon = 'info-circle';

      $f = $modules->get('InputfieldText');
      $f->name = 'description_selector';
      $f->label = __('Description selector');
      $f->description = __('This selector will be used to get current page description using [`$page->get()`](https://processwire.com/api/ref/page/get/) method.');
      $f->icon = 'search';
      $f->columnWidth = 50;
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldInteger');
      $f->name = 'description_max_length';
      $f->label = __('Description maximum length');
      $f->description = __('Description will be truncated to the specified number of characters.');
      $f->icon = 'text-width';
      $f->columnWidth = 25;
      $f->required = true;
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldSelect');
      $f->name = 'description_truncate_mode';
      $f->label = __('Description truncate mode');
      $f->description = __('Select truncate mode to use with [`$sanitizer->truncate()`](https://processwire.com/api/ref/sanitizer/truncate/) method.');
      $f->icon = 'cog';
      $f->columnWidth = 25;
      $f->required = true;
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $f->attr('value', $data[$f->name]);

      foreach ($truncateModes as $value => $label) {
        $f->addOption($value, $label);
      }

      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Image');
    $set->icon = 'image';
    $set->description = __('Used in `og:image` and `twitter:image` meta tags.');

      $f = $modules->get('InputfieldText');
      $f->name = 'image_selector';
      $f->label = __('Image selector');
      $f->description = __('This selector will be used to get image from the page using [`$page->get()`](https://processwire.com/api/ref/page/get/) method.');
      $f->icon = 'search';
      $f->columnWidth = 50;
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldInteger');
      $f->name = 'image_width';
      $f->label = __('Image width');
      $f->description = __('Image will be resized to specified width.');
      $f->icon = 'arrows-h';
      $f->columnWidth = 25;
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldInteger');
      $f->name = 'image_height';
      $f->label = __('Image height');
      $f->description = __('Image will be resized to specified height.');
      $f->icon = 'arrows-v';
      $f->columnWidth = 25;
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'image_alt_field';
      $f->label = __('Image object field name for alternative text');
      $f->description = __('The value of this field will be used as an alternative text of the image. Enable custom fields for your image field and specify the field name. [Read more about custom image fields](https://processwire.com/blog/posts/pw-3.0.142/).');
      $f->icon = 'cube';
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $f->attr('value', $data[$f->name]);
      $set->add($f);

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'image_inherit';
      $f->label = __('Inherit image from the nearest parent page (including home page)');
      $f->description = __('If the image cannot be found from the current page, the module will try to find the image from the nearest parent page.');
      $f->icon = 'sort-numeric-desc';
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldPageListSelect');
      $f->name = 'image_fallback_page';
      $f->label = __('Fallback page for image');
      $f->description = __('If the image cannot be found from the current page (and possibly enabled inheritance fails), the module will try to find the image from the given page. Use this to define default image for all pages. The selector defined above will be used to find the image.');
      $f->icon = 'life-ring';
      $f->attr('value', $data[$f->name]);
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Hreflang tags');
    $set->icon = 'language';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_hreflang';
      $f->label = __('Render hreflang tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldMarkup');
      $f->label = __('Instructions for setting up hreflang tags');
      $f->icon = 'info-circle';
      $f->value = '
        <p>
          '. __('To render hreflang tags, the following requirements must be met:') .'
        </p>
        <ol>
          <li>'. __('Your site has at least two languages set up') .'</li>
          <li>'. __('LanguageSupportPageNames module is installed') .'</li>
          <li>'. __('Field defined in "Hreflang language/region code field" exists and your language template includes that field.') .'</li>
          <li>'. __('Language code field is populated in every language page. If the language code field is empty, the hreflang tag will not be rendered for that language.') .'</li>
        </ol>
      ';
      $f->notes = __('[Read more about hreflang tags](https://support.google.com/webmasters/answer/189077?hl=en).');
      $f->showIf = 'render_hreflang=1';
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'hreflang_code_field';
      $f->label = __('Hreflang language/region code field');
      $f->description = __('The following field name will be used define language code for every language page.');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_hreflang=1';
      $f->showIf = 'render_hreflang=1';
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Open Graph tags');
    $set->icon = 'tags';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_og';
      $f->label = __('Render Open Graph tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'og_type';
      $f->label = __('Open Graph page type');
      $f->description = __('Open Graph type of the page/resource. Used in `og:type` meta tag.');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_og=1';
      $f->showIf = 'render_og=1';
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Twitter tags');
    $set->icon = 'twitter';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_twitter';
      $f->label = __('Render Twitter tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitter_card';
      $f->label = __('Twitter card type');
      $f->description = __('Twitter card type, which can be one of `summary`, `summary_large_image`, `app`, or `player`. Used in `twitter:card` meta tag.');
      $f->icon = 'id-card-o';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_twitter=1';
      $f->showIf = 'render_twitter=1';
      $f->notes = __('Default') .': '. $defaults[$f->name] . "\r ";
      $f->notes .= sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitter_site';
      $f->label = __('Twitter site');
      $f->description = __('Twitter user name. Used in `twitter:site` meta tag.');
      $f->icon = 'globe';
      $f->attr('value', $data[$f->name]);
      $f->showIf = 'render_twitter=1';
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitter_creator';
      $f->label = __('Twitter creator');
      $f->description = __('Twitter user name. Used in `twitter:creator` meta tag.');
      $f->icon = 'user';
      $f->attr('value', $data[$f->name]);
      $f->showIf = 'render_twitter=1';
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Facebook tags');
    $set->icon = 'facebook';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_facebook';
      $f->label = __('Render Facebook tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'facebook_app_id';
      $f->label = __('Facebook app ID');
      $f->description = __('Facebook application ID. Used in `fb:app_id` meta tag.');
      $f->icon = 'hashtag';
      $f->attr('value', $data[$f->name]);
      $f->showIf = 'render_facebook=1';
      $f->notes = sprintf(__('API: `$module->%s`'), $f->name);
      $set->add($f);

    $inputfields->add($set);

    return $inputfields;
  }
}
