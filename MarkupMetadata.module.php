<?php namespace ProcessWire;

class MarkupMetadata extends WireData implements Module, ConfigurableModule {

  public static function getModuleInfo() {
    return [
      'title' => 'Markup Metadata',
      'version' => 104,
      'summary' => 'Set and render meta tags for head section.',
      'author' => 'Nokikana / Ville Saarivaara',
      'singular' => true,
      'autoload' => false,
      'icon' => 'hashtag',
    ];
  }

  public static function getDefaultData() {
    return [
      'render_og' => 1,
      'render_twitter' => 1,
      'render_facebook' => 1,
      'render_hreflang' => 1,
      'siteName' => 'Site name',
      'domain' => 'http://domain.com',
      'charset' => 'utf-8',
      'viewport' => 'width=device-width, initial-scale=1.0',
      'og_type' => 'website',
      'pageTitleSelector' => 'title',
      'descriptionSelector' => 'summary',
      'hreflangCodeField' => 'languageCode',
      'twitterName' => '',
      'twitterCard' => 'summary_large_image',
      'facebookAppId' => '',
      'image' => null,
      'tags' => [],
    ];
  }

  public function __construct() {
    // Set default configuration
    foreach(self::getDefaultData() as $key => $val) {
      $this->$key = $val;
    }
  }

  private function load() {
    // Dynamic properties
    $this->pageTitle = ($this->pageTitle) ? $this->pageTitle : wire('page')->get($this->pageTitleSelector);
    $this->documentTitle = ($this->documentTitle) ? $this->documentTitle : $this->pageTitle .' - '. $this->siteName;
    $this->pageUrl = ($this->pageUrl) ? $this->pageUrl : $this->domain . wire('page')->url;
    $this->description = ($this->description) ? $this->description : wire('page')->get($this->descriptionSelector);

    // General tags
    if ($this->charset) $this->setMeta('charset', ['charset' => $this->charset]);
    if ($this->viewport) $this->setMeta('viewport', ['name' => 'viewport', 'content' => $this->viewport]);
    if ($this->description) $this->setMeta('description', ['name' => 'description', 'content' => $this->description]);

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
  }

  public function setMeta(String $key, Array $args) {
    $tags = $this->tags;

    foreach ($args as $arg => $val) {
      if ($val) {
        $tags[$key][$arg] = $val;
      } else {
        unset($tags[$key][$arg]);
      }
    }

    $this->tags = $tags;
  }

  public function removeMeta(String $key) {
    $tags = $this->tags;

    unset($tags[$key]);

    $this->tags = $tags;
  }

  public function renderMetaTags() {
    $out = '';

    foreach ($this->tags as $key => $args) {
      $argsMarkup = '';

      foreach ($args as $arg => $val) {
        $argsMarkup .= ($arg && $val) ? ' '. $arg .'="'. $val .'"' : '';
      }

      if (!$argsMarkup) continue;

      $out .= '<meta'. $argsMarkup .'>';
    }

    return $out;
  }

  public function renderHreflangLinks() {
    if (!$this->render_hreflang) return;

    $out = '';
    $languages = wire('languages')->find($this->hreflangCodeField .'!=""');

    if (count($languages) < 2) return;

    foreach ($languages as $l) {
      if (!wire('page')->viewable($l)) continue;

      $out .= '<link rel="alternate" href="'. $this->domain . wire('page')->localUrl($l) .'" hreflang="'. $l->{$this->hreflangCodeField} .'">';
    }

    return $out;
  }

  public function render() {
    // Load meta tags
    $this->load();

    $out = '
      <title>'. $this->documentTitle .'</title>
      '. $this->renderMetaTags() .'
      <link rel="canonical" href="'. $this->pageUrl .'">
      '. $this->renderHreflangLinks() .'
    ';

    return $out;
  }

  public static function getModuleConfigInputfields(Array $data) {
    // Merge data with default values
    $data = array_merge(self::getDefaultData(), $data);
    $defaults = self::getDefaultData();

    $modules = wire('modules');

    $inputfields = new InputfieldWrapper();

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Render toggles');
    $set->icon = 'sliders';

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_og';
      $f->label = __('Render Opengraph tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_twitter';
      $f->label = __('Render Twitter tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_facebook';
      $f->label = __('Render Facebook tags');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

      $f = $modules->get('InputfieldCheckbox');
      $f->name = 'render_hreflang';
      $f->label = __('Render hreflang tags');
      $f->notes = __('Make sure your language template includes the language code field (field name defined in "Field mapping" section below). Use this field to define language/region code for each language. If the language code field is empty, the hreflang tag will not be rendered. ');
      $f->notes .= __('Note that hreflang tags will be rendered only when your site has at least two languages set up. ');
      $f->notes .= __('[Read more about hreflang tags.](https://support.google.com/webmasters/answer/189077?hl=en)');
      $f->attr('checked', ($data[$f->name] ? 'checked' : ''));
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Site settings');
    $set->icon = 'home';

      $f = $modules->get('InputfieldText');
      $f->name = 'siteName';
      $f->label = __('Site name');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'domain';
      $f->label = __('Domain');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Default meta tags');
    $set->icon = 'hashtag';

      $f = $modules->get('InputfieldText');
      $f->name = 'charset';
      $f->label = __('Character set');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'viewport';
      $f->label = __('Viewport');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'og_type';
      $f->label = __('og:type');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Field mapping');
    $set->icon = 'link';

      $f = $modules->get('InputfieldText');
      $f->name = 'pageTitleSelector';
      $f->label = __('Page title selector');
      $f->attr('value', $data[$f->name]);
      $f->required = true;
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'descriptionSelector';
      $f->label = __('Description field');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'hreflangCodeField';
      $f->label = __('Hreflang language/region code field');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

    $inputfields->add($set);

    $set = $modules->get("InputfieldFieldset");
    $set->label = __('Social media configuration');
    $set->icon = 'share-alt';

      $f = $modules->get('InputfieldText');
      $f->name = 'twitterName';
      $f->label = __('Twitter name');
      $f->attr('value', $data[$f->name]);
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'twitterCard';
      $f->label = __('Twitter card type');
      $f->attr('value', $data[$f->name]);
      $f->notes = __('Default value') .': '. $defaults[$f->name];
      $set->add($f);

      $f = $modules->get('InputfieldText');
      $f->name = 'facebookAppId';
      $f->label = __('Facebook app ID');
      $f->attr('value', $data[$f->name]);
      $set->add($f);

    $inputfields->add($set);

    return $inputfields;
  }
}