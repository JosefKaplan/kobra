<?php declare(strict_types=1);

namespace App\Modules\Admin\User;

use App\Entity\User;
use App\Models\UserManager;
use App\Modules\Admin\BasePresenter;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Datagrid;
use App\Helpers\FormHelper;
use App\Modules\Front\FormFactory\FormFactory;
use Contributte\Datagrid\Exception\DatagridException;

class UserPresenter extends BasePresenter
{
	#[\Nette\DI\Attributes\Inject]
	public UserManager $userManager;

	#[\Nette\DI\Attributes\Inject]
	public FormFactory $formFactory;

	public function renderDefault(): void
	{
	}

    /**
     * @throws DatagridException
     */
    public function createComponentUsersGrid(string $name): Datagrid
	{
		$grid = new Datagrid($this, $name);

		$grid->setPrimaryKey('id');
		$grid->setDataSource($this->userManager->findAll());

		$grid->addColumnNumber('id', 'ID')
			->setSortable();

		$grid->addColumnText('username', FormHelper::LABEL_USERNAME)
			->setSortable()
			->setFilterText();

		$grid->addColumnText('firstName', FormHelper::LABEL_FIRST_NAME)
			->setSortable()
			->setFilterText();

		$grid->addColumnText('lastName', FormHelper::LABEL_LAST_NAME)
			->setSortable()
			->setFilterText();

		$grid->addColumnText('email', FormHelper::LABEL_EMAIL)
			->setSortable()
			->setFilterText();

		$grid->addColumnText('phone', FormHelper::LABEL_PHONE);

		$grid->addColumnText('role', 'Role')
			->setSortable()
			->setFilterSelect(['' => 'Vše', 'user' => 'user', 'admin' => 'admin']);

		$grid->addColumnText('isActive', 'Aktivní')
			->setRenderer(function ($item) {
				$active = $item instanceof User ? $item->isActive() : $item['isActive'];
				if ($active) {
					return \Nette\Utils\Html::el('span')->class('badge bg-success')->setText('Ano');
				}
				return \Nette\Utils\Html::el('span')->class('badge bg-danger')->setText('Ne');
			})
			->setSortable();

		if ($this->getUser()->isInRole('admin')) {

			$grid->addAction('edit', 'Upravit', 'edit')
				->setIcon('pencil')
				->setClass('btn btn-xs btn-outline-primary')
				->setRenderCondition(function ($item) {
					$user = $this->getUser();
					$itemId = $item instanceof User ? $item->getId() : $item['id'];
					if ($user->isInRole('admin') || $user->getId() === $itemId) {
						return true;
					}
					return false;
				});

			$grid->addAction('delete', 'Smazat', 'delete!')
				->setIcon('trash')
				->setClass('btn btn-xs btn-outline-danger ajax')
				->setConfirmation(new StringConfirmation('Opravdu smazat tohoto uživatele natrvalo? Nelze vrátit.'))
				->setRenderCondition(function ($item) {
					$itemId = $item instanceof User ? $item->getId() : $item['id'];
					return $this->getUser()->isInRole('admin') && $this->getUser()->getId() !== $itemId;
				});

			$grid->addToolbarButton('add', 'Nový uživatel')
				->setIcon('plus')
				->setClass('btn btn-primary');
		}

		$grid->setPagination(true);

		return $grid;
	}

	public function handleDelete(int $id): void
	{
		if (!$this->getUser()->isInRole('admin') || $this->getUser()->getId() === $id) {
			$this->error(FormHelper::ERR_ACCESS_DENIED);
		}

		$this->userManager->delete($id);
		$this->flashMessage(FormHelper::FLASH_USER_DELETED, 'success');

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['usersGrid']->reload();
		} else {
			$this->redirect('this');
		}
	}

	public function actionEdit(int $id): void
	{
		if (!$this->getUser()->isInRole('admin') && $this->getUser()->getId() !== $id) {
			$this->flashMessage(FormHelper::MSG_NO_PERMISSION_EDIT, 'danger');
			$this->redirect('default');
		}

		$userEntity = $this->userManager->getById($id);
		if (!$userEntity) {
			$this->error(FormHelper::ERR_USER_NOT_FOUND);
		}

		$this['userForm']->setDefaults([
			'firstName' => $userEntity->getFirstName(),
			'lastName' => $userEntity->getLastName(),
			'userName' => $userEntity->getUsername(),
			'email' => $userEntity->getEmail(),
			'phone' => $userEntity->getPhone(),
			'role' => $userEntity->getRole(),
			'isActive' => $userEntity->isActive(),
		]);
	}

	public function actionAdd(): void
	{
		if (!$this->getUser()->isInRole('admin')) {
			$this->flashMessage(FormHelper::MSG_ONLY_ADMIN_ADD, 'danger');
			$this->redirect('default');
		}
	}

	protected function createComponentUserForm(): \Nette\Application\UI\Form
	{
		$form = $this->formFactory->createUserForm($this->getUser());
		$form->onSuccess[] = [$this, 'userFormSucceeded'];
		return $form;
	}

	public function userFormSucceeded(\Nette\Application\UI\Form $form, \stdClass $data): void
	{
		$id = (int) $this->getParameter('id');

		try {
			$user = $this->getUser();
			if ($id) {
				// EDIT
				if (!$user->isInRole('admin') && $user->getId() !== $id) {
					$this->error(FormHelper::ERR_ACCESS_DENIED);
				}

				$updateData = [
					'username' => $data->userName,
					'first_name' => $data->firstName,
					'last_name' => $data->lastName,
					'email' => $data->email,
					'phone' => $data->phone,
				];

				if (isset($data->role)) {
					$updateData['role'] = $data->role;
				}
				if (isset($data->isActive)) {
					$updateData['is_active'] = $data->isActive;
				}

				if ($data->password) {
					$updateData['password'] = $data->password;
				}

				$this->userManager->update($id, $updateData);
				$this->flashMessage(FormHelper::FLASH_USER_UPDATED, 'success');
			} else {
				// ADD
				if (!$user->isInRole('admin')) {
					$this->error(FormHelper::ERR_ACCESS_DENIED);
				}

				$this->userManager->add(
					$data->userName,
					$data->firstName,
					$data->lastName,
					$data->email,
					$data->phone,
					$data->password
				);
				$this->flashMessage(FormHelper::FLASH_USER_CREATED, 'success');
			}

		} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
			$this->flashMessage(FormHelper::ERR_DUPLICATE_USER, 'danger');
			$this->redirect('this');
		} catch (\Exception $e) {
			$this->flashMessage(FormHelper::ERR_REGISTRATION, 'danger');
			$this->redirect('this');
		}

		$this->redirect('default');
	}
}
