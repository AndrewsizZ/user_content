<?php

namespace Drupal\user_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Controller for the user content endpoint.
 */
class UserContentController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a UserContentController object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Factory method to create a UserContentController instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new UserContentController instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Retrieves and returns the user's recent node IDs.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   * @param int $user_id
   *   The user ID for whom to retrieve the node IDs.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the user's recent node IDs.
   */
  public function getUserContent(Request $request, $user_id) {

    // Retrieve the last 50 node IDs authored by the current user.
    $node_ids = $this->getRecentUserNodeIds($user_id);

    // Create a cacheable JSON response.
    $response = new CacheableJsonResponse(['data' => $node_ids]);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray([
      '#cache' => [
        'tags' => ['user_content:' . $user_id],
      ],
    ]));

    // Return a JSON response.
    return $response;
  }

  /**
   * Retrieves the last 50 node IDs authored by the user.
   *
   * @param int $user_id
   *   The user ID for whom to retrieve the node IDs.
   *
   * @return array
   *   An array of the user's recent node IDs.
   */
  private function getRecentUserNodeIds($user_id) {

    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $user_id)
      ->sort('created', 'DESC')
      ->range(0, 50);

    $nids = $query->execute();

    return array_values($nids);
  }

  /**
    * Provides access control for the user content endpoint.
    *
    * @param int $user_id
    *   The user ID for whom to check access.
    *
    * @return bool
    *   TRUE if the user is logged in and the requested user is the current user,
    *   FALSE otherwise.
    *
    * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
    *   Thrown if the user is not logged in or the requested user is not the current user.
    */
  public static function accessUserContent($user_id) {
    // Check if the user is logged in.
    if (\Drupal::currentUser()->isAnonymous()) {
      return AccessResult::forbidden();
    }
  
    // Check if the requested user is the current user.
    if ($user_id != \Drupal::currentUser()->id()) {
      return AccessResult::forbidden();
    }
  
    return AccessResult::allowed();
  }
}
