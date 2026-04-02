<?php

defined('_JEXEC') or die;

use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

final class PlgSystemInartsMyAuthorFilter extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $form = $event->getForm();

        if (!\in_array($form->getName(), ['com_menus.item', 'com_menus.item.admin'], true)) {
            return;
        }

        if (!$this->isSupportedCategoryMenuForm($event->getData())) {
            return;
        }

        FormHelper::addFormPath(__DIR__ . '/forms');
        $form->loadFile('menu_filter', false);
    }

    public function onAfterRoute(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $user = $app->getIdentity();
        if ($user->guest) {
            return;
        }

        $menu = $app->getMenu()->getActive();
        if (!$menu || !$this->isSupportedCategoryMenuItem($menu)) {
            return;
        }

        $params = $menu->getParams();
        if (!(int) $params->get('inarts_author_filter_enabled', 0)) {
            return;
        }

        $author = trim((string) $user->name);
        if ($author === '') {
            return;
        }

        $current = trim($app->getInput()->getString('filter-search', ''));
        if ($current === $author) {
            return;
        }

        $uri = Uri::getInstance();
        $uri->setVar('filter-search', $author);

        $app->redirect((string) $uri);
        $app->close();
    }

    private function isSupportedCategoryMenuForm(mixed $data): bool
    {
        $query = $this->extractMenuQuery($data);

        return $this->isSupportedCategoryMenuQuery($query);
    }

    private function isSupportedCategoryMenuItem(object $menu): bool
    {
        $query = [];

        if (isset($menu->query) && \is_array($menu->query)) {
            $query = $menu->query;
        } elseif (isset($menu->link) && \is_string($menu->link)) {
            $query = $this->parseLinkQuery($menu->link);
        }

        return $this->isSupportedCategoryMenuQuery($query);
    }

    private function isSupportedCategoryMenuQuery(array $query): bool
    {
        if (($query['option'] ?? '') !== 'com_content') {
            return false;
        }

        if (($query['view'] ?? '') !== 'category') {
            return false;
        }

        return true;
    }

    private function extractMenuQuery(mixed $data): array
    {
        if (\is_object($data)) {
            if (isset($data->query) && \is_array($data->query)) {
                return $data->query;
            }

            if (isset($data->link) && \is_string($data->link)) {
                return $this->parseLinkQuery($data->link);
            }
        }

        if (\is_array($data)) {
            if (isset($data['query']) && \is_array($data['query'])) {
                return $data['query'];
            }

            if (isset($data['link']) && \is_string($data['link'])) {
                return $this->parseLinkQuery($data['link']);
            }
        }

        $input = Factory::getApplication()->getInput()->get('jform', [], 'array');

        if (isset($input['link']) && \is_string($input['link'])) {
            return $this->parseLinkQuery($input['link']);
        }

        return [];
    }

    private function parseLinkQuery(string $link): array
    {
        $query = [];
        $parts = parse_url($link);

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        return $query;
    }
}
