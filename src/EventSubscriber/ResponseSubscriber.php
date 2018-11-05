<?php

namespace Drupal\reporting\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class ReportToSubscriber.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * The Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The URL Generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  private $urlGenerator;

  /**
   * ResponseSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The URL Generator service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    UrlGeneratorInterface $urlGenerator
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->urlGenerator = $urlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => [
        'addReportToHeader',
      ],
    ];
  }

  /**
   * Add report-to header to the response.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event.
   */
  public function addReportToHeader(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    try {
      $entityStorage = $this->entityTypeManager->getStorage('reporting_endpoint');
    }
    catch (InvalidPluginDefinitionException $e) {
      return;
    }
    catch (PluginNotFoundException $e) {
      return;
    }

    if (!($result = $entityStorage->getQuery()->execute())) {
      return;
    }

    $endpoints = $entityStorage->loadMultiple($result);

    $header = [];

    foreach ($endpoints as $endpoint) {
      $url = $this->urlGenerator->generateFromRoute(
        'entity.reporting_endpoint.log',
        ['reporting_endpoint' => $endpoint->id()],
        // TODO Can local urls be relative?
        ['absolute' => TRUE]
      );
      $header[] = [
        'group' => $endpoint->id(),
        // TODO make max_age a property of config entity?
        'max_age' => 86400,
        'endpoints' => [['url' => $url]],
      ];
    }

    // The header’s value is interpreted as a JSON-formatted array of objects
    // without the outer [ and ].
    // @see https://w3c.github.io/reporting/#header
    $headerJson = trim(json_encode($header), '[]');

    $event->getResponse()->headers->set('Report-To', $headerJson);
  }

}
