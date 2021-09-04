<?php namespace ProcessWire;

class MarkupMetadata extends WireData implements Module, ConfigurableModule {

  /**
   * Module info
   *
   * @return Array
   */
  public static function getModuleInfo() : array {
    return [
      'title' => 'Markup Metadata',
      'version' => 108,
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
      'siteName' => 'Site name',
      'domain' => 'https://domain.com',
      'charset' => 'utf-8',
      'viewport' => 'width=device-width, initial-scale=1.0',
      'pageTitleSelector' => 'title',
      'descriptionSelector' => 'summary',
      'keywordsSelector' => 'keywords',
      'image' => null,
      'imageSelector' => null,
      'imageWidth' => 1200,
      'imageHeight' => 630,
      'render_hreflang' => 0,
      'hreflangCodeField' => 'languageCode',
      'render_og' => 1,
      'og_type' => 'website',
      'render_twitter' => 0,
      'twitterName' => '',
      'twitterCard' => 'summary_large_image',
      'render_facebook' => 0,
      'facebookAppId' => '',
      'tags' => [],
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
   * Load meta tags based on module configuration
   *
   * @return $this
   */
  private function load() {
    // Dynamic properties
    $this->pageTitle = $this->pageTitle ?? wire('page')->get($this->pageTitleSelector);
    $this->documentTitle = $this->documentTitle ?? $this->pageTitle .' - '. $this->siteName;
    $this->pageUrl = $this->pageUrl ?? $this->getPageUrl();
    $this->description = $this->description ?? wire('page')->get($this->descriptionSelector);
    $this->keywords = $this->keywords ?? wire('page')->get($this->keywordsSelector);

    // Image
    if (!$this->image && $this->imageSelector) {
      $this->image = wire('page')->get($this->imageSelector);

      if ($this->image && ($this->imageWidth || $this->imageHeight)) {
        if ($this->imageWidth && $this->imageHeight) {
          $this->image = $this->image->size($this->imageWidth, $this->imageHeight);
        } else if ($this->imageWidth) {
          $this->image = $this->image->width($this->imageWidth);
        } else {
          $this->image = $this->image->height($this->imageHeight);
        }
      }
    }

    // General tags
    if ($this->charset) $this->setMeta('charset', ['charset' => $this->charset]);
    if ($this->viewport) $this->setMeta('viewport', ['name' => 'viewport', 'content' => $this->viewport]);
    if ($this->description) $this->setMeta('description', ['name' => 'description', 'content' => $this->description]);
    if ($this->keywords) $this->setMeta('keywords', ['name' => 'keywords', 'content' => $this->keywords]);

    // Opengraph tags
    if ($this->render_og) {
      if ($this->pageTitle) $this->setMeta('og:title', ['property' => 'og:title', 'content' => $this->pageTitle]);
      if ($this->siteName) $this->setMeta('og:site_name', ['property' => 'og:site_name', 'content' => $this->siteName]);
      if ($this->og_type) $this->setMeta('og:type', ['property' => 'og:type', 'content' => $this->og_type]);
      if ($this->pageUrl) $this->setMeta('og:url', ['property' => 'og:url', 'content' => $this->pageUrl]);
      if ($this->description) $this->setMeta('og:description', ['property' => 'og:description', 'content' => $this->description]);

      // Opengraph image
      if ($this->image) {
        $this->setMeta('og:image', ['property' => 'og:image', 'content' => $this->image->httpUrl]);
        $this->setMeta('og:image:width', ['property' => 'og:image:width', 'content' => $this->image->width]);
        $this->setMeta('og:image:height', ['property' => 'og:image:height', 'content' => $this->image->height]);
      }
    }

    // Twitter tags
    if ($this->render_twitter) {
      if ($this->twitterCard) $this->setMeta('twitter:card', ['name' => 'twitter:card', 'content' => $this->twitterCard]);
      if ($this->twitterName) $this->setMeta('twitter:site', ['name' => 'twitter:site', 'content' => $this->twitterName]);
      if ($this->twitterName) $this->setMeta('twitter:creator', ['name' => 'twitter:creator', 'content' => $this->twitterName]);
      if ($this->pageTitle) $this->setMeta('twitter:title', ['name' => 'twitter:title', 'content' => $this->pageTitle]);
      if ($this->description) $this->setMeta('twitter:description', ['name' => 'twitter:description', 'content' => $this->description]);

      // Twitter image
      if ($this->image) {
        $this->setMeta('twitter:image', ['name' => 'twitter:image', 'content' => $this->image->httpUrl]);
      }
    }

    // Facebook tags
    if ($this->render_facebook) {
      if ($this->facebookAppId) $this->setMeta('fb:app_id', ['property' => 'fb:app_id', 'content' => $this->facebookAppId]);
    }

    return $this;
	}

  /**
   * Get current page URL
   *
   * @return string
   */
	private function getPageUrl () : string {
		$url = $this->domain . wire('page')->url;

		if (wire('input')->urlSegmentStr) {
			$url .= wire('input')->urlSegmentStr;

			if (wire('page')->template->slashUrlSegments == 1) {
				$url .= '/';
			}
		}

		return $url;
	}

  /**
   * Set a new meta tag
   *
   * @param String $key Unique identifier for tag. Will not be used in rendering.
   * @param Array $args An array containing HTML tag attributes in the following format: $name => $value.
   * @return Array $tags All defined tags
   */
  public function setMeta(String $key, Array $args) : array {
    $tags = $this->tags;

    foreach ($args as $arg => $val) {
      if (!empty($val)) {
        $tags[$key][$arg] = $val;
      } else {
        unset($tags[$key][$arg]);
      }
    }

    $this->tags = $tags;

    return $this->tags;
  }

  /**
   * Remove meta tag
   *
   * @param String $key Unique identifier for meta tag.
   * @return Array All defined tags
   */
  public function removeMeta(String $key) : ?array {
    $tags = $this->tags;

    if (!isset($tags[$key])) return null;

    unset($tags[$key]);

    $this->tags = $tags;

    return $this->tags;
  }

  /**
   * Render all custom meta tags
   *
   * @return String
   */
  public function renderMetaTags() : string {
    $out = '';

    foreach ($this->tags as $key => $args) {
      $argsMarkup = '';

      foreach ($args as $arg => $val) {
        $argsMarkup .= (!empty($arg) && !empty($val)) ? ' '. $arg .'="'. $val .'"' : '';
      }

      if (!$argsMarkup) continue;

      $out .= '<meta'. $argsMarkup .'>';
    }

    return $out;
  }

  /**
   * Render hreflang tags
   *
   * @return string
   */
  public function renderHreflangLinks() : ?string {
    if (!$this->render_hreflang) return null;

    // Make sure that PW language support is installed
    if (!wire('modules')->isInstalled('LanguageSupportPageNames')) return null;

    // Make sure that language template has language code field defined.
		if (!$this->user->language->template->hasField($this->hreflangCodeField)) return null;

    $out = '';
    $languages = wire('languages')->find($this->hreflangCodeField .'!=""');

    // We don't need hreflang tags if we got only one language in use
    if (count($languages) < 2) return null;

    foreach ($languages as $l) {
			if (!wire('page')->viewable($l)) continue;

      $out .= '<link rel="alternate" href="'. $this->domain . wire('page')->localUrl($l) .'" hreflang="'. $l->{$this->hreflangCodeField} .'">';
    }

    return $out;
  }

  /**
   * Render all metadata
   *
   * @return string
   */
  public function render() : string {
    // Load meta tags
    $this->load();

    return '
      <title>'. $this->documentTitle .'</title>
      '. $this->renderMetaTags() .'
      <link rel="canonical" href="'. $this->pageUrl .'">
      '. $this->renderHreflangLinks() .'
    ';
  }

  /**
   * Module configuration fields
   *
   * @param Array $data Current module configuration
   * @return \ProcessWire\InputfieldWrapper
   */
  public static function getModuleConfigInputfields(Array $data) : \ProcessWire\InputfieldWrapper {
    // Merge data with default values
    $data = array_merge(self::getDefaultData(), $data);
    $defaults = self::getDefaultData();

    $modules = wire('modules');

    $inputfields = new InputfieldWrapper();

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Site settings');
    $set->icon = 'home';

      $f = $modules->get('InputfieldText');
      $f->name = 'siteName';
      $f->label = __('Site name');
      $f->description = __('Value will be used to build document title.');
      $f->icon = 'home';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'domain';
      $f->label = __('Domain');
      $f->description = __('Used as a base for building the current page URL. Page URL with segments will be appended to the base URL.');
      $f->notes = __('Enter value without trailing slash.');
      $f->icon = 'globe';
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'charset';
      $f->label = __('Character set');
      $f->description = __('Used in *charset* meta tag.');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'viewport';
      $f->label = __('Viewport');
      $f->description = __('Used in *viewport* meta tag.');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Field mapping');
    $set->icon = 'link';

      $f = $modules->get('InputfieldText');
      $f->name = 'pageTitleSelector';
      $f->label = __('Page title selector');
      $f->description = __('The following selector will be used to get current page title using $page->get() method.');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'descriptionSelector';
      $f->label = __('Description selector');
      $f->description = __('The following selector will be used to get current page description using $page->get() method.');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'keywordsSelector';
      $f->label = __('Keywords selector');
      $f->description = __('The following selector will be used to get current page keywords using $page->get() method.');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $imageSet = $modules->get("InputfieldFieldset");
      $imageSet->label = __('Image');
      $imageSet->icon = 'image';
      $imageSet->description = __('Settings defined here only apply to dynamically populated meta images. If you set meta image directly using the "image" property, it will not be resized automatically.');

        $f = $modules->get('InputfieldText');
        $f->name = 'imageSelector';
        $f->label = __('Image selector');
        $f->columnWidth = 50;
        $f->attr('value', $data[$f->name]);
        $imageSet->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'imageWidth';
        $f->label = __('Image width');
        $f->columnWidth = 25;
        $f->attr('value', $data[$f->name]);
        $imageSet->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->name = 'imageHeight';
        $f->label = __('Image height');
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

      $f = $modules->get('InputfieldText');
      $f->name = 'hreflangCodeField';
      $f->label = __('Hreflang language/region code field');
      $f->description = __('The following field name will be used to get current language code. ');
      $f->description .= __('Make sure your **language template** includes this field. Use this field to define language/region code for each language. If the language code field is empty, the hreflang tag will not be rendered. ');
      $f->description .= __('Note that hreflang tags will be rendered only when your site has at least two languages set up. ');
      $f->description .= __('[Read more about hreflang tags.](https://support.google.com/webmasters/answer/189077?hl=en)');
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
      $f->name = 'twitterName';
      $f->label = __('Twitter name');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_twitter=1';
      $f->showIf = 'render_twitter=1';
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitterCard';
      $f->label = __('Twitter card type');
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
      $f->name = 'facebookAppId';
      $f->label = __('Facebook app ID');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->requiredIf = 'render_facebook=1';
      $f->showIf = 'render_facebook=1';
      $set->add($f);

    $inputfields->add($set);

    return $inputfields;
  }
}
