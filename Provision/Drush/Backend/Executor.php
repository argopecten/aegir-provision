<?php

/**
 * Platform-scoped backend command executor.
 */
class Provision_Drush_Backend_Executor {
  /**
   * Execute a command against a specific context object.
   *
   * @param string $target
   *   The context to operate on, @ prefix is optional.
   * @param string $command
   *   Drush command passed to drush_invoke_process().
   * @param array $arguments
   *   Drush arguments passed to drush_invoke_process().
   * @param array $data
   *   Drush data passed to drush_invoke_process().
   * @param string $mode
   *   Drush IPC mode (GET/POST) passed to drush_invoke_process().
   *
   * @return array
   *   Result array from drush_invoke_process().
   */
  public function invoke($target, $command, $arguments = array(), $data = array(), $mode = 'GET') {
    $context = '@' . ltrim($target, '@');
    $backend_options = array('method' => $mode, 'integrate' => TRUE, 'dispatch-using-alias' => TRUE);

    if ($context !== '@none') {
      $target_context = d($context, FALSE, FALSE);
      $platform_context = NULL;
      if (is_object($target_context) && $target_context->type === 'site') {
        $platform_context = $target_context->platform;
      }
      elseif (is_object($target_context) && $target_context->type === 'platform') {
        $platform_context = $target_context;
      }

      if ($platform_context) {
        $php_path = provision_platform_php_path($platform_context);
        if (empty($php_path) || !is_file($php_path) || !is_executable($php_path)) {
          drush_set_error('PROVISION_PLATFORM_PHP_MISSING',
            dt('No per-platform PHP binary found for @context.', array('@context' => $context)));
          return array('error_status' => 1, 'context' => array(), 'output' => '');
        }

        $drush_path = provision_platform_drush_path($platform_context);
        if (empty($drush_path)) {
          drush_set_error('PROVISION_PLATFORM_DRUSH_MISSING',
            dt('No per-platform Drush binary found for @context.', array('@context' => $context)));
          return array('error_status' => 1, 'context' => array(), 'output' => '');
        }
        $backend_options['drush'] = $drush_path;
        $backend_options['drush-script'] = $drush_path;
        $backend_options['php'] = $php_path;
        drush_log(dt('Using platform PHP/Drush for @context: @php @drush', array('@context' => $context, '@php' => $php_path, '@drush' => $drush_path)), 'debug');
      }
    }

    return drush_invoke_process($context, $command, $arguments, $data, $backend_options);
  }
}
