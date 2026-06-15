<?php

namespace Src\classes;

/**
 * Códigos de erro do registo — mensagens em português na view.
 */
class AuthRegisterFeedback
{
    public const CSRF_INVALID = 'csrf_invalid';
    public const NAME_REQUIRED = 'name_required';
    public const USER_TYPE_REQUIRED = 'user_type_required';
    public const DOCUMENT_REQUIRED = 'document_required';
    public const DOCUMENT_BI_INVALID = 'document_bi_invalid';
    public const DOCUMENT_NIF_INVALID = 'document_nif_invalid';
    public const DOCUMENT_TAKEN = 'document_taken';
    public const DOCUMENT_FILE_REQUIRED = 'document_file_required';
    public const DOCUMENT_FILE_INVALID = 'document_file_invalid';
    public const DOCUMENT_SAVE_FAILED = 'document_save_failed';
    public const EMAIL_REQUIRED = 'email_required';
    public const EMAIL_INVALID = 'email_invalid';
    public const EMAIL_TAKEN = 'email_taken';
    public const PHONE_REQUIRED = 'phone_required';
    public const PHONE_TAKEN = 'phone_taken';
    public const PASSWORD_SHORT = 'password_short';
    public const PASSWORD_MISMATCH = 'password_mismatch';
    public const PROFILE_PHOTO_INVALID = 'profile_photo_invalid';
    public const TERMS_NOT_ACCEPTED = 'terms_not_accepted';
    public const CREATE_FAILED = 'create_failed';

    private const MESSAGES = [
        self::CSRF_INVALID => 'Sessão expirada. Recarregue a página e tente novamente.',
        self::NAME_REQUIRED => 'Indique o seu nome ou razão social.',
        self::USER_TYPE_REQUIRED => 'Seleccione pessoa física ou jurídica.',
        self::DOCUMENT_REQUIRED => 'Indique o número de BI ou NIF.',
        self::DOCUMENT_BI_INVALID => 'Indique um BI ou NIF válido (letras e números, como no documento).',
        self::DOCUMENT_NIF_INVALID => 'O NIF de pessoa colectiva deve ter exactamente 10 dígitos.',
        self::DOCUMENT_TAKEN => 'Este número de identificação já está registado.',
        self::DOCUMENT_FILE_REQUIRED => 'Envie o documento de identificação (PDF ou imagem).',
        self::DOCUMENT_FILE_INVALID => '__dynamic_document_file_invalid__',
        self::DOCUMENT_SAVE_FAILED => 'Não foi possível guardar o documento. Tente outra vez.',
        self::EMAIL_REQUIRED => 'Indique o seu email.',
        self::EMAIL_INVALID => 'Email inválido.',
        self::EMAIL_TAKEN => 'Este email já está registado.',
        self::PHONE_REQUIRED => 'Indique o seu telefone.',
        self::PHONE_TAKEN => 'Este telefone já está registado.',
        self::PASSWORD_SHORT => 'A senha deve ter pelo menos 6 caracteres.',
        self::PASSWORD_MISMATCH => 'A confirmação de senha não coincide.',
        self::PROFILE_PHOTO_INVALID => 'Não foi possível usar a foto de perfil seleccionada.',
        self::TERMS_NOT_ACCEPTED => 'Deve aceitar os Termos e Condições e a Política de Privacidade para criar conta.',
        self::CREATE_FAILED => 'Não foi possível criar a conta. Tente novamente mais tarde.',
    ];

    public static function message(string $code): string
    {
        $message = self::MESSAGES[$code] ?? self::MESSAGES[self::CREATE_FAILED];
        if ($code === self::DOCUMENT_FILE_INVALID) {
            return 'Documento inválido. Use PDF ou imagem legível até '
                . UploadLimits::formatShort(UploadLimits::SERVER_MAX_BYTES) . '.';
        }

        return $message;
    }

    public static function isKnownCode(string $code): bool
    {
        return isset(self::MESSAGES[$code]);
    }

    /** Primeiro código de validação (vários erros → o mais relevante). */
    public static function pickPrimaryValidationCode(array $codes): string
    {
        $priority = [
            self::USER_TYPE_REQUIRED,
            self::DOCUMENT_REQUIRED,
            self::DOCUMENT_BI_INVALID,
            self::DOCUMENT_NIF_INVALID,
            self::DOCUMENT_TAKEN,
            self::EMAIL_REQUIRED,
            self::EMAIL_INVALID,
            self::EMAIL_TAKEN,
            self::PHONE_REQUIRED,
            self::PHONE_TAKEN,
            self::NAME_REQUIRED,
            self::PASSWORD_SHORT,
            self::PASSWORD_MISMATCH,
        ];

        foreach ($priority as $code) {
            if (in_array($code, $codes, true)) {
                return $code;
            }
        }

        return (string) ($codes[0] ?? self::CREATE_FAILED);
    }
}
