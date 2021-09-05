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
   * Module info
   *
   * @return Array
   */
  public static function getModuleInfo() : array {
    return [
      'title' => 'Markup Metadata',
      'version' => 110,
      'summary' => 'Set and render meta tags for head section.',
      'author' => 'Ville Fokke Saarivaara',
      'singular' => true,
      'autoload' => false,
      'icon' => 'hashtag',
      'requires' => [
        'ProcessWire>=3.0.0',
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
      'site_name' => 'Site name',
      'document_title_separator' => '-',
      'base_url' => 'https://domain.com',
      'charset' => 'utf-8',
      'viewport' => 'width=device-width, initial-scale=1.0',
      'page_title_selector' => 'title',
      'description_selector' => 'summary',
      'keywords_selector' => 'keywords',
      'image_selector' => 'image',
      'image_width' => 1200,
      'image_height' => 630,
      'render_hreflang' => 0,
      'hreflang_code_field' => 'languageCode',
      'render_og' => 1,
      'og_type' => 'website',
      'render_twitter' => 0,
      'twitter_name' => null,
      'twitter_card' => 'summary_large_image',
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

    // Add base URL
		$url = rtrim($this->base_url, '/');

    // Add page URL
    $url .= (!empty($language)) ? $this->page->localUrl($language) : $this->page->url;

    // Add URL segments
		if ($this->input->urlSegmentStr) {
			$url .= $this->input->urlSegmentStr;

			if ($this->page->template->slashUrlSegments == 1) {
				$url .= '/';
			}
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

    // Try to find image if it's not already defined
    if (
      empty($this->image) &&
      !empty($this->image_selector)
    ) {
      $imageResult = $this->page->get($this->image_selector);
      $image = null;

      if (!empty($imageResult)) {
        // If image field contains multiple images, get the first one
        if ($imageResult instanceof \ProcessWire\Pageimages) {
          $image = $imageResult->first();
        } else if ($imageResult instanceof \ProcessWire\Pageimage) {
          $image = $imageResult;
        } else {
          $image = null;
        }

        // Resize image
        if (!empty($image)) {
          if (!empty($this->image_width) && !empty($this->image_height)) {
            $this->image = $image->size($this->image_width, $this->image_height);
          } else if (!empty($this->image_width)) {
            $this->image = $image->width($this->image_width);
          } else if (!empty($this->image_height)) {
            $this->image = $image->height($this->image_height);
          } else {
            $this->image = null;
          }
        }
      }
    }

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
      }
    }

    // Twitter tags
    if ((bool) $this->render_twitter === true) {
      if (!empty($this->twitter_card)) $this->setMeta('meta', ['name' => 'twitter:card', 'content' => $this->twitter_card]);
      if (!empty($this->twitter_name)) {
        $this->setMeta('meta', ['name' => 'twitter:site', 'content' => $this->twitter_name]);
        $this->setMeta('meta', ['name' => 'twitter:creator', 'content' => $this->twitter_name]);
      }
      if (!empty($this->page_title)) $this->setMeta('meta', ['name' => 'twitter:title', 'content' => $this->page_title]);
      if (!empty($this->description)) $this->setMeta('meta', ['name' => 'twitter:description', 'content' => $this->description]);

      // Twitter image
      if (!empty($this->image)) {
        $this->setMeta('meta', ['name' => 'twitter:image', 'content' => $this->image->httpUrl]);
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

    $inputfields = new InputfieldWrapper();

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Site settings');
    $set->icon = 'home';

      $f = $modules->get('InputfieldText');
      $f->name = 'site_name';
      $f->label = __('Site name');
      $f->description = __('Value will be added to the document title after page title.');
      $f->icon = 'home';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'document_title_separator';
      $f->label = __('Document title separator');
      $f->description = __('Value will be used to separate page title and site name in document title.');
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $f->icon = 'ellipsis-h';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'base_url';
      $f->label = __('Base URL');
      $f->description = __('Used as a base for building the current page URL.');
      $f->notes = __('Enter value without trailing slash.');
      $f->icon = 'globe';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'charset';
      $f->label = __('Character set');
      $f->description = __('Used in *charset* meta tag.');
      $f->icon = 'keyboard-o';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'viewport';
      $f->label = __('Viewport');
      $f->description = __('Used in *viewport* meta tag.');
      $f->icon = 'desktop';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Field mapping');
    $set->icon = 'link';

      $f = $modules->get('InputfieldText');
      $f->name = 'page_title_selector';
      $f->label = __('Page title selector');
      $f->description = __('The following selector will be used to get current page title using $page->get() method.');
      $f->icon = 'header';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'description_selector';
      $f->label = __('Description selector');
      $f->description = __('The following selector will be used to get current page description using $page->get() method.');
      $f->icon = 'info-circle';
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'keywords_selector';
      $f->label = __('Keywords selector');
      $f->description = __('The following selector will be used to get current page keywords using $page->get() method.');
      $f->icon = 'tags';
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $imageSet = $modules->get("InputfieldFieldset");
      $imageSet->label = __('Images');
      $imageSet->icon = 'image';
      $imageSet->description = __('Settings defined here only apply to dynamically populated meta images. If you set meta image directly using the "image" property, it will not be resized automatically.');

        $f = $modules->get('InputfieldText');
        $f->name = 'image_selector';
        $f->label = __('Image selector');
        $f->icon = 'image';
        $f->notes = __('Default value') .': '. $defaults[$f->name];
        $f->columnWidth = 50;
        $f->attr('value', $data[$f->name]);
        $imageSet->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'image_width';
        $f->label = __('Image width');
        $f->icon = 'arrows-h';
        $f->notes = __('Default value') .': '. $defaults[$f->name];
        $f->columnWidth = 25;
        $f->attr('value', $data[$f->name]);
        $imageSet->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'image_height';
        $f->label = __('Image height');
        $f->icon = 'arrows-v';
        $f->notes = __('Default value') .': '. $defaults[$f->name];
        $f->columnWidth = 25;
        $f->attr('value', $data[$f->name]);
        $imageSet->add($f);

      $set->add($imageSet);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Hreflang tags');
    $set->icon = 'language';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_hreflang';
      $f->label = __('Render hreflang tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
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
      $f->notes = __('[Read more about hreflang tags.](https://support.google.com/webmasters/answer/189077?hl=en)');
      $f->showIf = 'render_hreflang=1';
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'hreflang_code_field';
      $f->label = __('Hreflang language/region code field');
      $f->description = __('The following field name will be used define language code for every language page.');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $f->required = true;
      $f->requiredIf = 'render_hreflang=1';
      $f->showIf = 'render_hreflang=1';
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Open Graph tags');
    $set->icon = 'tags';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_og';
      $f->label = __('Render Open Graph tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'og_type';
      $f->label = __('Open Graph site type');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $f->required = true;
      $f->requiredIf = 'render_og=1';
      $f->showIf = 'render_og=1';
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Twitter tags');
    $set->icon = 'twitter';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_twitter';
      $f->label = __('Render Twitter tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitter_name';
      $f->label = __('Twitter name');
      $f->icon = 'user';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_twitter=1';
      $f->showIf = 'render_twitter=1';
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitter_card';
      $f->label = __('Twitter card type');
      $f->icon = 'id-card-o';
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $f->required = true;
      $f->requiredIf = 'render_twitter=1';
      $f->showIf = 'render_twitter=1';
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Facebook tags');
    $set->icon = 'facebook';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_facebook';
      $f->label = __('Render Facebook tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'facebook_app_id';
      $f->label = __('Facebook app ID');
      $f->icon = 'hashtag';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_facebook=1';
      $f->showIf = 'render_facebook=1';
      $set->add($f);

    $inputfields->add($set);

    return $inputfields;
  }
}
