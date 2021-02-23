<?php
/**
 * @var tiFy\Contracts\Form\FormFactory $form
 * @var tiFy\Contracts\Form\FieldDriver $field
 * @var tiFy\Contracts\Form\MailerAddonDriver $addon
 * @var tiFy\Contracts\Mail\MailableView $this
 * @var array $params
 */
?>
<tr class="rowBodyContent">
    <td>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr class="rowBodyContent-section rowBodyContent-section--header">
                <td>
                    <h1 class="Title--1">
                        <?php echo $this->param('subject'); ?>
                    </h1>
                </td>
            </tr>

            <tr class="rowBodyContent-section rowBodyContent-section--body">
                <?php if ($fields = $this->get('fields', [])) : ?>
                    <td>
                        <table cellspacing="0" cellpadding="0" border="0" width="100%">
                            <?php foreach ($fields as $field) : ?>
                                <tr>
                                    <?php if ($label = $field->params('addons.mailer.label')) : ?>
                                        <td width="40%" valign="top">
                                            <b><?php echo $label; ?></b>
                                        </td>
                                        <td width="60%" valign="top">
                                            <?php echo $field->params('addons.mailer.value'); ?>
                                        </td>
                                    <?php else : ?>
                                        <td colspan="2" width="100%" valign="top">
                                            <?php echo $field->params('addon.mailer.value'); ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </td>
                <?php endif; ?>
            </tr>

            <tr class="rowBodyContent-section rowBodyContent-section--footer">
                <td></td>
            </tr>
        </table>
    </td>
</tr>