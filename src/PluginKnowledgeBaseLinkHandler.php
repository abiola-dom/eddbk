<?php

namespace RebelCode\EddBookings\Core;

use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Psr\EventManager\EventInterface;

/**
 * The handler for adding a link to the knowledge base for the plugin's row on the WordPress Plugins page.
 *
 * @since 0.1
 */
class PluginKnowledgeBaseLinkHandler implements InvocableInterface
{
    /* @since 0.1 */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since 0.1 */
    use StringTranslatingTrait;

    /**
     * {@inheritdoc}
     *
     * @since 0.1
     */
    public function __invoke()
    {
        $event = func_get_arg(0);

        if (!($event instanceof EventInterface)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Argument is not an event instance.')
            );
        }

        $links = $event->getParam(0);
        $file  = $event->getParam(1);

        if (strpos($file, 'eddbk/plugin.php') !== false) {
            $links['knowledge-base'] = sprintf(
                '<a href="https://docs.eddbookings.com/" target="_blank">%s</a>',
                $this->__('Visit knowledge base')
            );
        }

        $event->setParams([0 => $links] + $event->getParams());
    }
}
