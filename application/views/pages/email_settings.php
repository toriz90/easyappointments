<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="email-settings-page" class="container backend-page">
    <div id="email-settings">
        <div class="row">
            <div class="col-sm-3 offset-sm-1">
                <?php component('settings_nav'); ?>
            </div>
            <div class="col-sm-6">
                <form>
                    <fieldset>
                        <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                            <h4 class="text-black-50 mb-0 fw-light">
                                Configuración de Correo (SMTP)
                            </h4>
                            <?php if (can('edit', PRIV_SYSTEM_SETTINGS)): ?>
                                <div class="d-flex gap-2">
                                    <button type="button" id="test-email" class="btn btn-outline-secondary">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Enviar prueba
                                    </button>
                                    <button type="button" id="save-settings" class="btn btn-primary">
                                        <i class="fas fa-check-square me-2"></i>
                                        <?= lang('save') ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row mb-5">
                            <div class="col-12">
                                <h5 class="text-black-50 mb-3 fw-light">Servidor SMTP</h5>

                                <div class="row">
                                    <div class="col-8 mb-3">
                                        <label class="form-label" for="smtp-host">
                                            Servidor SMTP <span class="text-danger">*</span>
                                        </label>
                                        <input id="smtp-host" data-field="smtp_host"
                                               class="required form-control" placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-4 mb-3">
                                        <label class="form-label" for="smtp-port">
                                            Puerto <span class="text-danger">*</span>
                                        </label>
                                        <input id="smtp-port" data-field="smtp_port" type="number"
                                               class="required form-control" placeholder="587">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="smtp-crypto">Cifrado</label>
                                    <select id="smtp-crypto" data-field="smtp_crypto" class="form-control">
                                        <option value="tls">TLS (recomendado)</option>
                                        <option value="ssl">SSL</option>
                                        <option value="">Ninguno</option>
                                    </select>
                                </div>

                                <h5 class="text-black-50 mb-3 mt-4 fw-light">Autenticación</h5>

                                <div class="mb-3">
                                    <label class="form-label" for="smtp-user">
                                        Usuario / Correo <span class="text-danger">*</span>
                                    </label>
                                    <input id="smtp-user" data-field="smtp_user" type="email"
                                           class="required form-control" placeholder="hola@tudominio.com">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="smtp-pass">
                                        Contraseña / App Password
                                    </label>
                                    <input id="smtp-pass" data-field="smtp_pass" type="password"
                                           class="form-control" placeholder="Dejar vacío para no cambiar"
                                           autocomplete="new-password">
                                    <div class="form-text text-muted">
                                        <small>Para Gmail usa un App Password de 16 caracteres (sin espacios).</small>
                                    </div>
                                </div>

                                <h5 class="text-black-50 mb-3 mt-4 fw-light">Remitente</h5>

                                <div class="mb-3">
                                    <label class="form-label" for="from-name">
                                        Nombre del remitente <span class="text-danger">*</span>
                                    </label>
                                    <input id="from-name" data-field="from_name"
                                           class="required form-control" placeholder="Mi Empresa">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="from-address">
                                        Correo del remitente <span class="text-danger">*</span>
                                    </label>
                                    <input id="from-address" data-field="from_address" type="email"
                                           class="required form-control" placeholder="hola@tudominio.com">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="reply-to">Responder a</label>
                                    <input id="reply-to" data-field="reply_to" type="email"
                                           class="form-control" placeholder="hola@tudominio.com">
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="test-email-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enviar correo de prueba</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="test-email-recipient">Destinatario</label>
                <input id="test-email-recipient" type="email" class="form-control"
                       placeholder="correo@ejemplo.com">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="send-test-email" class="btn btn-primary">Enviar</button>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/email_settings_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/email_settings.js') ?>"></script>
<?php end_section('scripts'); ?>
