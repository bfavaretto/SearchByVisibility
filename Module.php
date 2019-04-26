<?php
/**
 * AdvancedSearchPlus
 *
 * Add some fields to the advanced search form (before/after creation date, has
 * media, etc.).
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace SearchByVisibility;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            // TODO Add user.
        ];
        foreach ($adapters as $adapter) {
            // Adjust resource search
            $sharedEventManager->attach(
                $adapter,
                'api.search.pre',
                [$this, 'searchQuery']
            );
        }

        // Add field to admin advanced search page.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );
        }

        // Add search filter to search result UI
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
            );
        }
    }

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function searchQuery(Event $event)
    {
        $query = $event->getParam('request')->getContent();
        
        // Remove is_public from the query if the value is '', before
        // passing it to AbstractResourceEntityAdapter, as it would
        // coerce it to boolean false for the query builder.
        if (isset($query['is_public'])) {
            if ($query['is_public'] === '') {
                unset($query['is_public']);
                $event->getParam('request')->setContent($query);
            } 
        } 
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event)
    {
        $query = $event->getParam('query', []);
        $query['datetime'] = isset($query['datetime']) ? $query['datetime'] : '';

        $partials = $event->getParam('partials', []);
        $partials[] = 'common/advanced-search-visibility';

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event)
    {
        $translate = $event->getTarget()->plugin('translate');
        $query = $event->getParam('query', []);
        $filters = $event->getParam('filters');

        if (isset($query['is_public']) && $query['is_public'] !== '') {
            $value = $query['is_public'] === '0' ? $translate('Private') : $translate('Public');
            $filters[$translate('Visibility')][] = $value;
            $event->setParam('filters', $filters);
        }
    }

    
}
