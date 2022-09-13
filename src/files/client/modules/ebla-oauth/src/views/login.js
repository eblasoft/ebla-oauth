define('ebla-oauth:views/login', ['views/login'], function (Dep) {

    return Dep.extend({

        events: {
            'click a[data-action="azure"]': function () {
                this.connect()
            },
            ...Dep.prototype.events
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.oAuthMethod = this.getConfig().get('oAuthMethod');

            if (this.getConfig().get('authenticationMethod') !== 'OAuth' || !this.oAuthMethod) return;

            this.wait(true);

            let url = `ExternalAccount/action/getOAuth2Info?method=${this.oAuthMethod}`;

            if (this.getMetadata().get(['app', 'oAuthProviders', this.oAuthMethod, 'infoUrl'])) {
                url = this.getMetadata().get(['app', 'oAuthProviders', this.oAuthMethod, 'infoUrl']);
            }

            $.ajax({
                url: url,
                dataType: 'json'
            }).done(response => {
                this.oAuthInfo = response;
                this.wait(false);
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.oAuthMethod) return;

            this.$el.find('.panel-body form')
                .append($('<a />')
                    .addClass('btn btn-xx-wide')
                    .attr('href', 'javascript:')
                    .attr('data-action', this.oAuthMethod.toLowerCase())
                    .css({
                        color: '#fff',
                        margin: '10px auto',
                        'background-color': '#000',
                        padding: '5px',
                    })
                    .html('Sign in with Microsoft'));
        },

        disableForm: function () {
            Dep.prototype.disableForm.call(this);

            this.$el.find(`a[data-action="${this.oAuthMethod}"]`).addClass('disabled').attr('disabled', 'disabled');
        },

        undisableForm: function () {
            Dep.prototype.undisableForm.call(this);

            this.$el.find(`a[data-action="${this.oAuthMethod}"]`).removeClass('disabled').removeAttr('disabled');
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

        connect: function () {
            this.disableForm();

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            this.popup(this.oAuthInfo, (res) => {
                let userName = '_oAuthCode';
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
                    login: true,
                    headers: {
                        'Authorization': 'Basic ' + authString,
                        'Espo-Authorization': authString,
                        'Espo-Authorization-By-Token': false,
                        'Espo-Authorization-Create-Token-Secret': true,
                    },
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
