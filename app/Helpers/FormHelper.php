<?php

declare(strict_types=1);

namespace App\Helpers;

class FormHelper
{
	# LABELS
	public const LABEL_FIRST_NAME = 'Jméno';
	public const LABEL_LAST_NAME = 'Příjmení';
	public const LABEL_USERNAME = 'Uživatelské jméno';
	public const LABEL_EMAIL = 'E-mail';
	public const LABEL_PHONE = 'Telefonní číslo';
	public const LABEL_PASSWORD = 'Heslo';
	public const LABEL_PASSWORD_SECURE = 'Bezpečné heslo';
	public const LABEL_REMEMBER = 'Zůstat trvale přihlášený';
	public const LABEL_SUBMIT_LOGIN = 'Přihlásit se';
	public const LABEL_SUBMIT_REGISTER = 'Zaregistrovat se';

	# REQUIRED
	public const REQUIRED_FIRST_NAME = 'Vyplňte prosím křestní jméno.';
	public const REQUIRED_LAST_NAME = 'Vyplňte prosím příjmení.';
	public const REQUIRED_USERNAME = 'Zadejte prosím své uživatelské jméno.';
	public const REQUIRED_EMAIL = 'Zadejte e-mailovou adresu.';
	public const REQUIRED_PHONE = 'Potřebujeme znát i váš telefon.';
	public const REQUIRED_PASSWORD = 'Zadejte prosím své heslo.';
	public const REQUIRED_PASSWORD_SECURE = 'Pro registraci musíte zadat bezpečné heslo.';

	# VALIDATION RULES & VALUES
	public const MSG_NAME_MIN_LENGTH = 'Uživatelské jméno musí mít alespoň %d znaky.';
	public const VAL_NAME_MIN_LENGTH = 3;

	public const MSG_LOGIN_PASSWORD_MIN_LENGTH = 'Heslo musí mít alespoň %d znaků.';
	public const VAL_LOGIN_PASSWORD_MIN_LENGTH = 6;

	public const MSG_REG_PASSWORD_MIN_LENGTH = 'Heslo musí mít alespoň %d znaků.';
	public const VAL_REG_PASSWORD_MIN_LENGTH = 8;

	public const MSG_REG_PASSWORD_PATTERN = 'Bezpečné heslo musí obsahovat alespoň jednu číslici.';
	public const VAL_REG_PASSWORD_PATTERN = '.*[0-9].*';

	public const MSG_EMAIL_INVALID = 'Špatný formát emailu.';

	public const MSG_PHONE_PATTERN = 'Zadejte telefonní číslo ve validním formátu (např. +420 123 456 789).';
	public const VAL_PHONE_PATTERN = '^[+]?[0-9\s]+$';

	# ERRORS & PERMISSIONS
	public const ERR_CSRF = 'Platnost formuláře vypršela, odešlete jej prosím znovu.';
	public const ERR_DUPLICATE_USER = 'Uživatel se stejným jménem nebo e-mailem už existuje.';
	public const ERR_REGISTRATION = 'Při registraci došlo k chybě. Omlouváme se.';
	public const ERR_ACCESS_DENIED = 'Přístup odepřen.';
	public const ERR_USER_NOT_FOUND = 'Uživatel nebyl nalezen.';
	public const MSG_NO_PERMISSION_EDIT = 'Nemáte oprávnění upravovat cizí profily.';
	public const MSG_ONLY_ADMIN_ADD = 'Pouze administrátor může přidávat uživatele.';

	# FLASH MESSAGES
	public const FLASH_USER_UPDATED = 'Údaje byly úspěšně aktualizovány.';
	public const FLASH_USER_CREATED = 'Uživatel byl úspěšně vytvořen.';
	public const FLASH_LOGIN_SUCCESS = 'Úspěšné přihlášení.';
	public const FLASH_LOGOUT_SUCCESS = 'Byli jste úspěšně odhlášeni.';
	public const FLASH_REGISTRATION_SUCCESS = 'Registrace proběhla úspěšně! Nyní se můžete přihlásit.';
	public const FLASH_USER_DELETED = 'Uživatel byl úspěšně smazán.';

	# ADDITIONAL LABELS
	public const LABEL_ROLE = 'Role';
	public const LABEL_IS_ACTIVE = 'Účet je aktivní';
	public const LABEL_SUBMIT_USER_SAVE = 'Uložit údaje';
}
