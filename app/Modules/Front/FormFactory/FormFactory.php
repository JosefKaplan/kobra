<?php declare(strict_types=1);

namespace App\Modules\Front\FormFactory;

use Nette\Application\UI\Form;
use Nette\Security\User;
use App\Helpers\FormHelper;

class FormFactory
{
    public function createLoginForm(): Form
    {
        $form = new Form;
        $form->addProtection(FormHelper::ERR_CSRF);

        $form->addText('userName', FormHelper::LABEL_USERNAME)
            ->setRequired(FormHelper::REQUIRED_USERNAME);

        $form->addPassword('password', FormHelper::LABEL_PASSWORD_SECURE)
            ->setRequired(FormHelper::REQUIRED_PASSWORD_SECURE);

        $form->addCheckbox('remember', FormHelper::LABEL_REMEMBER);

        $form->addSubmit('submit', FormHelper::LABEL_SUBMIT_USER_SAVE);

        return $form;
    }

    public function createRegisterForm(): Form
    {
        $form = new Form;
        $form->addProtection(FormHelper::ERR_CSRF);

        $this->addPersonalFields($form);
        $this->addPasswordField($form);

        $form->addSubmit('submit', FormHelper::LABEL_SUBMIT_USER_SAVE);

        return $form;
    }

    public function createUserForm(User $user): Form
    {
        $form = new Form;
        $form->addProtection(FormHelper::ERR_CSRF);

        $this->addPersonalFields($form);
        $this->addPasswordField($form);

        if ($user->isInRole('admin')) {
            $form->addSelect('role', FormHelper::LABEL_ROLE, [
                'user' => 'Uživatel',
                'admin' => 'Administrátor'
            ]);
            $form->addCheckbox('isActive', FormHelper::LABEL_IS_ACTIVE);
        }

        $form->addSubmit('submit', FormHelper::LABEL_SUBMIT_USER_SAVE);

        return $form;
    }

    private function addPersonalFields(Form $form): void
    {
        $form->addText('firstName', FormHelper::LABEL_FIRST_NAME)
            ->setRequired(FormHelper::REQUIRED_FIRST_NAME);

        $form->addText('lastName', FormHelper::LABEL_LAST_NAME)
            ->setRequired(FormHelper::REQUIRED_LAST_NAME);

        $form->addText('userName', FormHelper::LABEL_USERNAME)
            ->setRequired(FormHelper::REQUIRED_USERNAME)
            ->addRule($form::MIN_LENGTH, FormHelper::MSG_NAME_MIN_LENGTH, FormHelper::VAL_NAME_MIN_LENGTH);

        $form->addText('email', FormHelper::LABEL_EMAIL)
            ->setRequired(FormHelper::REQUIRED_EMAIL)
            ->addRule($form::EMAIL, FormHelper::MSG_EMAIL_INVALID);

        $form->addText('phone', FormHelper::LABEL_PHONE)
            ->setRequired(FormHelper::REQUIRED_PHONE)
            ->addRule($form::PATTERN, FormHelper::MSG_PHONE_PATTERN, FormHelper::VAL_PHONE_PATTERN);
    }

    private function addPasswordField(Form $form): void
    {
        $form->addPassword('password', FormHelper::LABEL_PASSWORD_SECURE)
            ->setRequired(FormHelper::REQUIRED_PASSWORD_SECURE)
            ->addRule($form::MIN_LENGTH, FormHelper::MSG_REG_PASSWORD_MIN_LENGTH, FormHelper::VAL_REG_PASSWORD_MIN_LENGTH)
            ->addRule($form::PATTERN, FormHelper::MSG_REG_PASSWORD_PATTERN, FormHelper::VAL_REG_PASSWORD_PATTERN);
    }
}