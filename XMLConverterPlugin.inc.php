<?php

import('lib.pkp.classes.plugins.GenericPlugin');

class xmlConverterPlugin extends GenericPlugin
{

	function register($category, $path, $mainContextId = null)
	{

		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled()) {
				$javaChecker = exec('command java --version >/dev/null && echo "yes" || echo "no"');
				if($javaChecker=='yes') {
					HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
					HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));

					$this->_registerTemplateResource();
				}
			}
			return true;
		}
		return false;
	}

	function getPluginUrl($request) {
		return $request->getBaseUrl() . '/' . $this->getPluginPath();
	}
	public function getDisplayName()
	{
		return __('plugins.generic.xmlConverter.displayName');
	}

	public function getDescription()
	{
		return __('plugins.generic.xmlConverter.description');
	}

	public function templateFetchCallback($hookName, $params)
	{
		$request = $this->getRequest();
		$dispatcher = $request->getDispatcher();

		$templateMgr = $params[0];
		$resourceName = $params[1];
		$allowedRoles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_SITE_ADMIN];

		if ($resourceName == 'controllers/grid/gridRow.tpl') {

			$row = $templateMgr->getTemplateVars('row');
			$data = $row->getData();

			if (is_array($data) && (isset($data['submissionFile']))) {
				$submissionFile = $data['submissionFile'];
				$fileExtension = strtolower($submissionFile->getData('mimetype'));

				// Ensure that the conversion is run on the appropriate workflow stage
				$stageId = (int)$request->getUserVar('stageId');
				$submissionId = $submissionFile->getData('submissionId');
				$submission = Services::get('submission')->get($submissionId);
				$submissionStageId = $submission->getData('stageId');

				$roles = $request->getUser()->getRoles($request->getContext()->getId());
				$extensionSupported = in_array(strtolower($fileExtension), static::getSupportedMimetypes());
				$stageAllowed = in_array($stageId, $this->getAllowedWorkflowStages());
				$workflowAllowed = in_array($submissionStageId, $this->getAllowedWorkflowStages());

				$accessAllowed = false;
				foreach ($roles as $role) {
					if (in_array($role->getId(), $allowedRoles)) {
						$accessAllowed = true;
						break;
					}
				}

				if ($extensionSupported && $accessAllowed && $stageAllowed && 	$workflowAllowed)
				{
					$path = $dispatcher->url($request, ROUTE_PAGE, null, 'xmlConverterConverter', 'convert', null,
						array(
							'submissionId' => $submissionId,
							'fileId' => $submissionFile->getData('fileId'),
							'stageId' => $stageId
						));
					$pathRedirect = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'access',
						array(
							'submissionId' => $submissionId,
							'fileId' => $submissionFile->getData('fileId'),
							'stageId' => $stageId
						));

					import('lib.pkp.classes.linkAction.request.AjaxAction');
					$linkAction = new LinkAction(
						'convertxmlConverter',
						new PostAndRedirectAction($path, $pathRedirect),
						__('plugins.generic.xmlConverter.button.convertToTei')
					);
					$row->addAction($linkAction);
				}

				}

			}
		}



	public static function getSupportedMimetypes()
	{
		return ['text/xml', 'text/html', 'application/xml'];
	}

	public function getAllowedWorkflowStages()
	{
		return [
			WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION];
	}

	public function callbackLoadHandler(string $hookName, array $args): bool
	{
		$page = $args[0];
		$op = $args[1];
		if($page && $args) {
			$pageOperator = "$page/$op";
			switch ($pageOperator) {
				case "xmlConverterConverter/convert":
					$this->import('handlers/XMLConverterHandler');
					define('HANDLER_CLASS', 'xmlConverterHandler');
					return true;
				default:
					break;
			}
		}

		return false;
	}


}