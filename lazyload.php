<?php
/**
 * @package    lazyload
 *
 * @author     Daniel Stone <contact@danielstone.dev>
 * @copyright  A copyright
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://danielstone.dev
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * Lazyload plugin.
 *
 * @package   lazyload
 * @since     1.0.0
 */
class plgSystemLazyload extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  1.0.0
	 */
	protected $app;

    /**
     * List of subscribed events for this plugin
     *
     * @return string[]
     * @since 1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'onBeforeCompileHead',
            'onAfterRender' => 'onAfterRender',
        ];
    }

    /**
     * Add the lazy load js file
     *
     * @return void
     * @since 1.0.0
     */
    public function onBeforeCompileHead()
    {
        if (!$this->app->isClient('site') || $this->app->getDocument()->getType() !== 'html') {
            return;
        }

        $this->app->getDocument()
            ->getWebAssetManager()
            ->registerAndUseScript(
                'plg-lazyload-js',
                $this->params->get('lazyload_script_url')
            );
    }

    /**
     * Alter all images that are loaded in href tags to data-src tags
     *
     * @return  void
     * @throws DOMException
     * @since 1.0.0
     */
    public function onAfterRender()
    {
        if (!$this->app->isClient('site') || $this->app->getDocument()->getType() !== 'html') {
            return;
        }

        $loadingGif = $this->params->get('loading_gif');

        $documentObject = new DOMDocument();
        @$documentObject->loadHTML(mb_convert_encoding($this->app->getBody(), 'HTML-ENTITIES', 'UTF-8'));

        /** @var DOMNode $image */
        foreach ($documentObject->getElementsByTagName('img') as $image) {
            $class = $image->getAttribute('class') ?? '';
            if ($class === 'logo-img' || $image->hasAttribute('data-lazyload-ignore')) {
                continue;
            }

            $newImage = $image->cloneNode($image->hasChildNodes());
            $newImage->setAttribute('class', $class . ' lazy');
            $newImage->setAttribute('data-src', $image->getAttribute('src'));
            $newImage->setAttribute('src', $loadingGif);

            $image->parentNode->replaceChild($newImage, $image);
        }

        // Load the lazy load script at the bottom of the page, after all content is in the dom, to lazy load all images
        // that are taggable as such
        $script = $documentObject->createElement('script');
        $script->append('var lazyLoadInstance = new LazyLoad();');
        $documentObject->append($script);

        $this->app->setBody($documentObject->saveHTML());
    }
}
