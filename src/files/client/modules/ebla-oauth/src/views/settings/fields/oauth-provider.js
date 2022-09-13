define('ebla-oauth:views/settings/fields/oauth-provider', ['views/fields/enum'], function (Dep) {

    return Dep.extend({

        fetchEmptyValueAsNull: true,

        setupOptions: function () {
            this.params.options = Object.keys(
                this.getMetadata().get(['app', 'oAuthProviders']) || {}
            );

            if (!this.model.get(this.name)) {
                this.params.options.unshift('');
            }
        },
    });
});
