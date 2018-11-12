<?php

namespace Drupal\reporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\reporting\Entity\ReportingEndpointInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * ReportingEndpoint Controller.
 */
class ReportingEndpoint extends ControllerBase {

  /**
   * The Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Create a new Report URI Controller.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Logger channel.
   */
  public function __construct(RequestStack $requestStack, LoggerInterface $logger) {
    $this->requestStack = $requestStack;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('logger.factory')->get('reporting')
    );
  }

  /**
   * Handle a report submission.
   *
   * @param \Drupal\reporting\Entity\ReportingEndpointInterface $reporting_endpoint
   *   The reporting endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response with the appropriate response code.
   */
  public function log(ReportingEndpointInterface $reporting_endpoint) {

    // Return 410: Gone if endpoint is disabled.
    // @see https://w3c.github.io/reporting/#try-delivery
    if (!$reporting_endpoint->status()) {
      return new Response('', 410);
    }

    $reportJson = $this->requestStack->getCurrentRequest()->getContent();
    $report = json_decode($reportJson);

    // Return 400: Bad Request if content cannot be parsed.
    if (empty($report) || json_last_error() != JSON_ERROR_NONE) {
      return new Response('', 400);
    }

    $this->logger
      ->info("@endpoint <br/>\n<pre>@data</pre>", [
        '@endpoint' => $reporting_endpoint->id(),
        '@data' => json_encode($report, JSON_PRETTY_PRINT),
      ]);

    // 202: Accepted.
    return new Response('', 202);
  }

}
