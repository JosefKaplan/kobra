<?php declare(strict_types=1);

namespace App\Modules\Front\Auth;

use App\Modules\Front\BasePresenter;
use Nette\Application\UI\Form;
use App\Helpers\FormHelper;

class AuthPresenter extends BasePresenter
{
	#[\Nette\Application\Attributes\Persistent]
	public string $backlink = '';

	#[\Nette\DI\Attributes\Inject]
	public \App\Models\UserManager $userManager;

	#[\Nette\DI\Attributes\Inject]
	public \App\Modules\Front\FormFactory\FormFactory $formFactory;

	public function createComponentLoginForm(): Form
	{
		$form = $this->formFactory->createLoginForm();
		$form['submit']->caption = FormHelper::LABEL_SUBMIT_LOGIN;
		$form->onSuccess[] = [$this, 'onLoginFormSuccess'];
		return $form;
	}

	public function onLoginFormSuccess(Form $form, \stdClass $data): void
	{
		try {
			if ($data->remember) {
				$this->getUser()->setExpiration('14 days');
			} else {
				$this->getUser()->setExpiration('1 hour');
			}

			$this->getUser()->login($data->userName, $data->password);

			$this->flashMessage(FormHelper::FLASH_LOGIN_SUCCESS, 'success');
			$this->restoreRequest($this->backlink);
			$this->redirect(':Admin:User:default');

		} catch (\Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	public function createComponentRegisterForm(): Form
	{
		$form = $this->formFactory->createRegisterForm();
		$form['submit']->caption = FormHelper::LABEL_SUBMIT_REGISTER;

		$form->onSuccess[] = [$this, 'onRegisterFormSuccess'];

		return $form;
	}

	public function onRegisterFormSuccess(Form $form, \stdClass $data): void
	{
		if ($this->userManager->isDuplicate($data->userName, $data->email)) {
			$form->addError(FormHelper::ERR_DUPLICATE_USER);
			return;
		}

		try {
			$this->userManager->add(
				$data->userName,
				$data->firstName,
				$data->lastName,
				$data->email,
				$data->phone,
				$data->password
			);
		} catch (\Exception $e) {
			$form->addError(FormHelper::ERR_REGISTRATION);
			return;
		}

		$this->flashMessage(FormHelper::FLASH_REGISTRATION_SUCCESS, 'success');
		$this->redirect('login');
	}

	public function renderDefault(): void
	{
		$this->redirect('login');
	}

	public function actionLogin(): void
	{
		$this->checkNotAuthenticated();
	}

	public function actionRegister(): void
	{
		$this->checkNotAuthenticated();
	}

	public function actionLogout(): void
	{
		$this->getUser()->logout();
		$this->flashMessage(FormHelper::FLASH_LOGOUT_SUCCESS, 'info');
		$this->redirect('login');
	}
}
