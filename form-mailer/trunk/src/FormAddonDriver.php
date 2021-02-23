<?php

declare(strict_types=1);

namespace Pollen\FormMailer;

use Closure;
use Pollen\Form\AddonDriver;
use Pollen\Form\AddonDriverInterface;
use Pollen\Form\FieldDriverInterface;
use Pollen\Validation\Validator as v;

class FormAddonDriver extends AddonDriver implements FormAddonDriverInterface
{
    /**
     * @inheritDoc
     */
    public function boot(): AddonDriverInterface
    {
        if (!$this->isBooted()) {
            parent::boot();

            $this->form()->events()
                ->on(
                    'handle.validated',
                    function () {
                        if ($debug = $this->params('debug')) {
                            $this->form()->event('addon.mailer.email.debug');
                        }
                    }
                )
                ->on(
                    'handle.successful',
                    function () {
                        $this->form()->event('addon.mailer.email.send');
                    }
                )
                ->on('addon.mailer.email.debug', [$this, 'emailDebug'])
                ->on('addon.mailer.email.send', [$this, 'emailSend'])
                ->on(
                    'fields.booted',
                    function () {
                        if ($this->form()->field('mail')) {
                            $emitter = '%%mail%%';
                        } elseif ($this->form()->field('email')) {
                            $emitter = '%%email%%';
                        } else {
                            $emitter = null;
                        }

                        if ($notification = $this->params('notification')) {
                            $defaults = [
                                'reply-to' => $emitter,
                            ];
                            $this->params(
                                [
                                    'notification' => is_array($notification)
                                        ? array_merge($defaults, $notification) : $defaults,
                                ]
                            );
                        }

                        if ($confirmation = $this->params('confirmation')) {
                            $defaults = [
                                'to' => $emitter,
                            ];
                            $this->params(
                                [
                                    'confirmation' => is_array($confirmation)
                                        ? array_merge($defaults, $confirmation) : $defaults,
                                ]
                            );
                        }
                    }
                );

            if ($admin = $this->params('admin')) {
                $defaultAdmin = [
                    'confirmation' => true,
                    'notification' => true,
                ];
                $this->params(['admin' => is_array($admin) ? array_merge($defaultAdmin, $admin) : $defaultAdmin]);
            }

            if ($this->params('admin.confirmation') || $this->params('admin.notification')) {
                $optionNamesBase = $this->params('option_names_base', '') ?: "mail_{$this->form()->getAlias()}";

                Metabox::add(
                    md5("FormAddonMailer-{$this->form()->getAlias()}"),
                    [
                        'title' => $this->form()->getTitle(),
                    ],
                    'tify_options@options',
                    'tab'
                );

                if ($this->params('admin.notification')) {
                    $optionName = $optionNamesBase . '_notif';

                    register_setting(
                        'tify_options@options',
                        $optionName,
                        function ($value) {
                            $sender = $value['sender'] ?? null;

                            if (!empty($sender['email']) && !v::email()->validate($sender['email'])) {
                                add_settings_error(
                                    "mail_{$this->form()->getAlias()}_notif_sender",
                                    'invalid_email',
                                    __(
                                        'Adresse de messagerie d\'expédition de la notification non valide.',
                                        'tify'
                                    )
                                );
                            }
                            if ($recipients = $value['recipients'] ?? null) {
                                foreach ($recipients as $k => $recip) {
                                    if (empty($recip['email'])) {
                                        add_settings_error(
                                            "mail_{$this->form()->getAlias()}_notif_recep{$k}",
                                            'empty_email',
                                            __(
                                                'Adresse de messagerie de destination de la notification non renseignée.',
                                                'tify'
                                            )
                                        );
                                    } elseif (!is_email($recip['email'])) {
                                        add_settings_error(
                                            "mail_{$this->form()->getAlias()}_notif_recep{$k}",
                                            'invalid_email',
                                            __(
                                                'Adresse de messagerie de destination de la notification non valide.',
                                                'tify'
                                            )
                                        );
                                    }
                                }
                            }
                            return $value;
                        }
                    );

                    Metabox::add(
                        md5("FormAddonMailerNotification-{$this->form()->getAlias()}"),
                        [
                            'driver'   => 'mail-config',
                            'name'     => $optionName,
                            'params'   => [
                                'info' => '<span class="dashicons dashicons-info-outline"></span>&nbsp;' .
                                    __('Message de notification à destination des gestionnaires de demandes.', 'tify'),
                            ],
                            'parent'   => md5("FormAddonMailer-{$this->form()->getAlias()}"),
                            'position' => 1,
                            'title'    => __('Message(s) de notification(s)', 'tify'),
                        ],
                        'tify_options@options',
                        'tab'
                    );

                    $optionValues = get_option($optionName);
                    if ($optionValues !== false) {
                        if (filter_var($optionValues['enabled'], FILTER_VALIDATE_BOOL)) {
                            if (!empty($optionValues['sender']['email'])) {
                                $this->params(
                                    [
                                        'notification.from' => [
                                            $optionValues['sender']['email'],
                                            $optionValues['sender']['name'] ?? '',
                                        ],
                                    ]
                                );
                            }
                            if (!empty($optionValues['recipients'])) {
                                $this->params(
                                    [
                                        'notification.to' => $optionValues['recipients'],
                                    ]
                                );
                            }
                        }
                    }
                }

                if ($this->params('admin.confirmation')) {
                    $optionName = $optionNamesBase . '_conf';

                    register_setting(
                        'tify_options@options',
                        $optionName,
                        function ($value) {
                            $sender = $value['sender'] ?? null;

                            if (!empty($sender['email']) && !v::email()->validate($sender['email'])) {
                                add_settings_error(
                                    "mail_{$this->form()->getAlias()}_notif_sender",
                                    'invalid_email',
                                    __(
                                        'Adresse de messagerie d\'expédition de la confirmation n\'est pas valide.',
                                        'tify'
                                    )
                                );
                            }
                            return $value;
                        }
                    );

                    Metabox::add(
                        md5("FormAddonMailerConfirmation-{$this->form()->getAlias()}"),
                        [
                            'driver'   => 'mail-config',
                            'name'     => $optionName,
                            'params'   => [
                                'enabled' => [
                                    'recipients' => false,
                                ],
                                'info'    => '<span class="dashicons dashicons-info-outline"></span>&nbsp;' .
                                    __('Message de confirmation à destination de l\'emetteur de la demande.', 'tify'),
                            ],
                            'parent'   => md5("FormAddonMailer-{$this->form()->getAlias()}"),
                            'position' => 2,
                            'title'    => __('Message de confirmation', 'tify'),
                        ],
                        'tify_options@options',
                        'tab'
                    );

                    $optionValues = get_option($optionName);
                    if ($optionValues !== false) {
                        if (filter_var($optionValues['enabled'], FILTER_VALIDATE_BOOL)) {
                            if (!empty($optionValues['sender']['email'])) {
                                $this->params(
                                    [
                                        'confirmation.from' => [
                                            $optionValues['sender']['email'],
                                            $optionValues['sender']['name'] ?? '',
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function defaultParams(): array
    {
        return [
            /**
             * Activation de l'interface d'administration
             * @var bool|string true: Activer tous|false: Désactiver tous|"confirmation"|"notification"
             */
            'admin'             => true,
            /**
             * Activation du mode de debogage
             * @var bool|string true: Activer défaut|false: Désactiver|"confirmation"|"notification".
             * Notification par défaut.
             */
            'debug'             => false,
            /**
             * Paramètres du message de notification.
             * @see \tiFy\Mail\Mailer
             * @var bool|array true: Activer défaut|false: Désactiver
             */
            'notification'      => true,
            /**
             * Paramètres du message de notification.
             * @see \tiFy\Mail\Mailer
             * @var bool|array
             */
            'confirmation'      => true,
            /**
             * Base du nom d'enregistrement de la données en base.
             * @var string|null Automatique si null
             */
            'option_names_base' => null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function defaultFieldOptions(): array
    {
        return [
            /**
             * Activation de l'affichage dans le mail automatique.
             * @var bool
             */
            'show'  => true,
            /**
             * Intitulé de qualification dans le mail automatique.
             * @var bool
             */
            'label' => function (FieldDriverInterface $field) {
                return $field->getTitle();
            },
            /**
             * Valeur dans le mail automatique.
             * @var bool
             */
            'value' => function (FieldDriverInterface $field) {
                return $field->getValues();
            },
        ];
    }

    /**
     * Débogguage des emails de confirmation et/ou de notification.
     *
     * @return void
     */
    public function emailDebug()
    {
        switch ($this->params('debug')) {
            default:
            case 'notification' :
                $notification = $this->params('notification');
                if ($notification !== false) {
                    Mail::debug($this->mailParams($notification, 'notification'));
                } else {
                    wp_die(
                        __('Email de notification désactivé.', 'tify'),
                        __('FormAddonMailer - Erreur', 'tify'),
                        500
                    );
                }
                break;
            case 'confirmation' :
                $confirmation = $this->params('confirmation');
                if ($confirmation !== false) {
                    Mail::debug($this->mailParams($confirmation, 'confirmation'));
                } else {
                    wp_die(
                        __('Email de confirmation désactivé.', 'tify'),
                        __('FormAddonMailer - Erreur', 'tify'),
                        500
                    );
                }
                break;
        }
    }

    /**
     * Expédition des emails de confirmation et/ou de notification.
     *
     * @return void
     */
    public function emailSend(): void
    {
        if ($notification = $this->params('notification')) {
            Mail::send($this->mailParams($notification, 'notification'));
        }

        if ($confirmation = $this->params('confirmation')) {
            Mail::send($this->mailParams($confirmation, 'confirmation'));
        }
    }

    /**
     * Traitement des paramètres de configuration de l'email.
     *
     * @param array $params
     * @param string $type notification|confirmation.
     *
     * @return array
     */
    public function mailParams(array $params, string $type): array
    {
        $params['subject'] = $params['subject']
            ?? sprintf(__('%1$s - Demande de contact', 'tify'), get_bloginfo('name'));

        $params = array_map([$this->form()->fields(), 'metatagsValue'], $params);

        $params['to'] = $params['to'] ?? Mail::getDefaults('to');
        $params['from'] = $params['from'] ?? Mail::getDefaults('from');

        $fields = $this->form()->fields()->collect()->filter(
            function (FieldDriverInterface $field) {
                return $field->getAddonOption('mailer', 'show') && $field->supports('request');
            }
        );

        $fields->each(
            function (FieldDriverInterface $field) {
                $label = $field->getAddonOption('mailer', 'label');
                $field->params(['addons.mailer.label' => $label instanceof Closure ? $label($field) : $label]);

                $value = $field->getAddonOption('mailer', 'value');
                $field->params(['addons.mailer.value' => $value instanceof Closure ? $value($field) : $value]);
            }
        );

        $form = $this->form();
        $addon = $this;
        $params['data'] = array_merge(
            Mail::config('data', []),
            compact('addon', 'form', 'fields', 'params'),
            $params['data'] ?? []
        );

        if (!isset($params['viewer']['override_dir'])) {
            $params['viewer'] = array_merge(
                [
                    'override_dir' => $this->form()->formManager()->resources("/views/addon/mailer/mail/{$type}"),
                ],
                $params['viewer'] ?? []
            );
        }

        return $params;
    }
}