define('ebla-oauth:views/login', ['views/login'], function (Dep) {

    return Dep.extend({

        events: {
            'click a[data-action="oauth"] ': function (e) {
                const provider = $(e.currentTarget).data('id');

                this.connect(provider);
            },
            ...Dep.prototype.events
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.oAuthProviders = this.getConfig().get('oAuthProviders') || [];

            if (!this.oAuthProviders.length) return;

            this.wait(true);

            $.ajax({
                url: 'ExternalAccount/action/getOAuth2Info',
                dataType: 'json'
            }).done(response => {
                this.oAuthProviderData = response || {};

                this.wait(false);
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.oAuthProviderData) return;

            Object.keys(this.oAuthProviderData).forEach(item => {
                this.$el.find('.panel-body form')
                    .append(
                        $('<div />')
                            .append(
                                $('<a />')
                                    .addClass('btn btn-xx-wide')
                                    .attr('href', 'javascript:')
                                    .attr('data-action', 'oauth')
                                    .attr('data-id', item)
                                    .css({
                                        color: '#fff',
                                        margin: '10px auto',
                                        'background-color': this.oAuthProviderData[item]['color'],
                                        padding: '5px',
                                    })
                                    .html(this.oAuthProviderData[item]['buttonTitle'])));
            });
        },

        disableForm: function () {
            Dep.prototype.disableForm.call(this);

            this.$el.find('a[data-action="oauth"]').addClass('disabled').attr('disabled', 'disabled');
        },

        undisableForm: function () {
            Dep.prototype.undisableForm.call(this);

            this.$el.find('a[data-action="oauth"]').removeClass('disabled').removeAttr('disabled');
        },

        popup: function (options, callback) {
            options.windowName = options.windowName || 'ConnectWithOAuth';
            options.windowOptions = options.windowOptions || 'location=0,status=0,width=800,height=400';
            options.callback = options.callback || function () {
                window.location.reload();
            };

            const self = this;

            let path = options.path;
            const urlParams = [];
            const params = (options.params || {});
            for (let name in params) {
                if (params[name]) {
                    urlParams.push(name + '=' + encodeURI(params[name]));
                }
            }
            path += '?' + urlParams.join('&');

            const parseUrl = this.getParseUrl();

            const popup = window.open(path, options.windowName, options.windowOptions);
            const interval = window.setInterval(() => {
                if (popup.closed) {
                    window.clearInterval(interval);

                    self.undisableForm();
                    self.notify(false);
                } else {
                    let res = parseUrl(popup.location.href.toString());
                    if (res) {
                        callback.call(self, res);
                        popup.close();
                        window.clearInterval(interval);
                    }
                }
            }, 500);
        },

        getParseUrl: function () {
            return function (str) {
                const data = {};

                str = str.substr(str.indexOf('?') + 1, str.length);
                str.split('&').forEach(function (part) {
                    const arr = part.split('=');
                    const name = decodeURI(arr[0]);
                    data[name] = decodeURI(arr[1] || '');
                }, this);

                if (!data.error && !data.code) {
                    return null;
                }

                return data;
            };
        },

        connect: function (provider) {
            this.disableForm();

            const providerData = this.oAuthProviderData[provider];

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            this.popup(providerData, (res) => {
                let userName = '$' + provider;
                let password = res.code;
                let authString = '';

                try {
                    authString = btoa(userName + ':' + password);
                } catch (e) {
                    Espo.Ui.error(this.translate('Error') + ': ' + e.message, true);
                    this.undisableForm();
                    throw e;
                }

                Espo.Ajax.getRequest('App/user', null, {
                    headers: {
                        'Authorization': 'Basic ' + authString,
                        'Espo-Authorization': authString,
                        'Espo-Authorization-By-Token': false,
                        'Espo-Authorization-Create-Token-Secret': true,
                    },
                    login: true,
                }).then(data => {
                    this.notify(false);

                    this.trigger('login', data.user.userName, data);
                }).catch(xhr => {
                    this.undisableForm();

                    if (xhr.status === 401) {
                        let data = {};
                        if ('responseJSON' in xhr) {
                            data = xhr.responseJSON || {};
                        }

                        let statusReason = xhr.getResponseHeader('X-Status-Reason');

                        if (statusReason === 'second-step-required') {
                            xhr.errorIsHandled = true;

                            this.onSecondStepRequired(userName, password, data);

                            return;
                        }

                        this.onWrongCredentials();
                    }
                });
            });
        },
    });
});
