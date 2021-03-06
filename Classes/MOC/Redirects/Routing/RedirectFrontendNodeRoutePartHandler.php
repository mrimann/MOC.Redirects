<?php
namespace MOC\Redirects\Routing;

use Doctrine\ORM\QueryBuilder;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Mvc\Routing\DynamicRoutePart;
use TYPO3\TYPO3CR\Domain\Model\NodeData;

/**
 * A route part handler for finding nodes specifically in the website's frontend.
 */
class RedirectFrontendNodeRoutePartHandler extends DynamicRoutePart {

	/**
	 * @Flow\Inject
	 * @var \Doctrine\Common\Persistence\ObjectManager
	 */
	protected $entityManager;

	/**
	 * @Flow\Inject
	 * @var Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @param string $requestPath The request path to be matched
	 * @return string value to match
	 */
	protected function findValueToMatch($requestPath) {
		return $requestPath;
	}

	/**
	 * @param string $requestPath
	 * @return boolean TRUE if the $requestPath could be matched, otherwise FALSE
	 */
	protected function matchValue($requestPath) {
		/** @var Uri $uri */
		$uri = $this->bootstrap->getActiveRequestHandler()->getHttpRequest()->getUri();
		$relativeUrl = $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');
		$absoluteUrl = $uri->getHost() . $relativeUrl;

		/** @var QueryBuilder $queryBuilder */
		$queryBuilder = $this->entityManager->createQueryBuilder();

		$queryBuilder->select('n')
			->distinct()
			->from('TYPO3\TYPO3CR\Domain\Model\NodeData', 'n')
			->where('n.workspace = :workspace')
			->setParameter('workspace', 'live')
			->andWhere('n.properties LIKE :relativeUrl')
			->setParameter('relativeUrl', '%' . $relativeUrl . '%');

		$query = $queryBuilder->getQuery();
		$nodes = $query->getResult();

		if (empty($nodes)) {
			return FALSE;
		}

		foreach ($nodes as $node) {
			/** @var NodeData $node */
			// Prevent partial matches
			$redirectUrl =  preg_replace('#^https?://#', '', $node->getProperty('redirectUrl'));
			if ($redirectUrl === $absoluteUrl || $redirectUrl === $relativeUrl) {
				$matchingNode = $node;
				break;
			}
		}

		if (!isset($matchingNode)) {
			return FALSE;
		}

		$this->setName('node');
		$this->value = $matchingNode->getPath();

		return TRUE;
	}

	/**
	 * @param string $value value to resolve
	 * @return boolean TRUE if value could be resolved successfully, otherwise FALSE.
	 */
	protected function resolveValue($value) {
		return FALSE;
	}

}