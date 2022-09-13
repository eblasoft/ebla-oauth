define('ebla-oauth:views/admin/integrations/oauth2', ['views/admin/integrations/oauth2'], function (Dep) {

    return Dep.extend({

            setup: function () {
                Dep.prototype.setup.call(this);

                this.integration = this.options.integration;

                this.redirectUri = this.getConfig().get('siteUrl').replace(/\/$/, '') + '/oauth-callback.php';

                this.helpText = false;
                if (this.getLanguage().has(this.integration, 'help', 'Integration')) {
                    this.helpText = this.translate(this.integration, 'help', 'Integration');

                    if (this.getHelper().transfromMarkdownText) {
                        this.helpText = this.getHelper().transfromMarkdownText(this.helpText, {});
                    }
                }
            }
    });
});
