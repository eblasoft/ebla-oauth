define('ebla-oauth:views/settings/fields/oauth-provider', ['views/fields/multi-enum'], function (Dep) {

    return Dep.extend({

        fetchEmptyValueAsNull: true,

        setupOptions: function () {
            this.params.options = Object.keys(
                this.getMetadata().get(['app', 'oAuthProviders']) || {}
            );
        },
    });
});
