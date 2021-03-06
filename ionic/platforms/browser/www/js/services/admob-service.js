/*global
 angular, ionic, _
 */

/**
 * options: {
    "ios_weight": {
        "app": 0.58,
        "platform": 0.42
    },
    "android_weight": {
        "app": 0.23,
        "platform": 0.77
    },
    "app": {
        "ios": {
            "banner_id": "app-ios-banner",
            "interstitial_id": "app-ios-inter",
            "banner": false,
            "interstitial": true,
            "videos": false
        },
        "android": {
            "banner_id": "app-android-banner",
            "interstitial_id": "app-android-inter",
            "banner": true,
            "interstitial": false,
            "videos": false
        }
    },
    "platform": {
        "ios": {
            "banner_id": "owner-ios-banner",
            "interstitial_id": "owner-ios-inter",
            "banner": true,
            "interstitial": false,
            "videos": false
        },
        "android": {
            "banner_id": "owner-android-banner",
            "interstitial_id": "owner-android-inter",
            "banner": true,
            "interstitial": false,
            "videos": false
        }
    }
}
 */
angular.module('starter').service('AdmobService', function ($log, $rootScope) {
    var service = {
        interstitialWeights: {
            start: {
                'show': 0.333,
                'skip': 0.667
            },
            medium: {
                'show': 0.25,
                'skip': 0.75
            },
            low: {
                'show': 0.05,
                'skip': 0.95
            },
            default: {
                'show': 0.15,
                'skip': 0.85
            }
        },
        interstitialState: 'start',
        viewEnterCount: 0,
        options: {},
        forbiddenStates: [],
        canReloadBanner: true,
        currentPlatform: null,
        interstitialPromise: null,
        lastBannerId: null,
        lastInterstitialId: null,
        lastRewardedVideoId: null,
        willShowInterstitial: false
    };

    service.getWeight = function (probs) {
        var random = _.random(0, 1000);
        var offset = 0;
        var keyUsed = 'start';
        var match = false;
        _.forEach(probs, function (value, key) {
            offset = offset + (value * 1000);
            if (!match && (random <= offset)) {
                keyUsed = key;
                match = true;
            }
        });
        $log.debug('AdMob key used: ', keyUsed);
        return keyUsed;
    };

    service.init = function (options) {
        if ($rootScope.isNativeApp && admob) {
            if (ionic.Platform.isIOS()) {
                service.currentPlatform = 'ios';
                $log.debug('AdMob init iOS');
                service.options = options.app.ios;
                service.initWithOptions(options);
            }

            if (ionic.Platform.isAndroid()) {
                service.currentPlatform = 'android';
                $log.debug('AdMob init Android');
                service.options = options.app.android;
                service.initWithOptions(options);
            }

            // Ionic view enter is global (if banner and/or interstitial are enabled)
            if (service.options.banner || service.options.interstitial) {
                $rootScope.$on('$ionicView.enter', function (event, data) {
                    $log.info('admob $ionicView.enter.');

                    // Check for any forbidden stateName
                    service.canReloadBanner = false;
                    if (service.forbiddenStates.indexOf(data.stateName) !== -1) {
                        service.removeBanner();
                        $log.info('admob $ionicView.enter forbidden state.', data.stateName);
                    } else {
                        service.canReloadBanner = true;
                        $log.info('admob $ionicView.enter service.canReloadBanner = true;');
                    }

                    service.viewEnterCount = service.viewEnterCount + 1;

                    // After 10 views, increase chances to show an Interstitial ad!
                    if (service.viewEnterCount >= 9) {
                        service.interstitialState = 'medium';
                    }

                    var action = service.getWeight(service.interstitialWeights[service.interstitialState]);

                    $log.info('admob action', action);

                    if (service.willShowInterstitial === false && action === 'show') {
                        service.willShowInterstitial = true;
                        $log.info('admob action service.willShowInterstitial = true;');
                    }

                    if (service.willShowInterstitial) {
                        $log.info('admob enter service.willShowInterstitial');
                        try {
                            if (service.interstitialPromise !== null) {
                                $log.info('service.interstitialPromise !== null');
                                service.interstitialPromise.then(function () {
                                    $log.info('service.interstitialPromise.then OK');
                                    document.addEventListener('admob.interstitial.close', service._reload);
                                    admob.interstitial.show();
                                    service.willShowInterstitial = false;

                                    /** On success, we change the randomness */
                                    if (service.interstitialState === 'start') {
                                        service.interstitialState = 'low';
                                    } else {
                                        service.interstitialState = 'default';
                                    }

                                    service.viewEnterCount = 0;

                                    /** Then prepare the next one. */
                                    service.preloadInterstitial();
                                }, function () {
                                    $log.error('Failed to load interstitial! (Promise)');
                                    $log.info('service.interstitialPromise.then KO');
                                    service.willShowInterstitial = false;
                                    /** Then prepare the next one. */
                                    service.preloadInterstitial();
                                });
                            } else {
                                service.preloadInterstitial();
                            }
                        } catch (e) {
                            $log.error('Interstitial failed to show (Exception)');
                            service.willShowInterstitial = false;
                            /** Then prepare the next one. */
                            service.preloadInterstitial();
                        }
                    } else {
                        service._reload();
                    }
                });
            }

            // Extensive logging of admob
            if (service.options.banner) {
                document.addEventListener('admob.banner.load', () => {
                    $log.info('admob banner loaded.');
                });
                document.addEventListener('admob.banner.load_fail', () => {
                    $log.info('admob banner load failed.');
                });
                document.addEventListener('admob.banner.open', () => {
                    $log.info('admob banner opened.');
                });
                document.addEventListener('admob.banner.exit_app', () => {
                    $log.info('admob banner exit_app.');
                });
                document.addEventListener('admob.banner.close', () => {
                    $log.info('admob banner return app (close).');
                });
            }

            // Extensive logging of admob
            if (service.options.interstitial) {
                document.addEventListener('admob.interstitial.load', () => {
                    $log.info('admob interstitial loaded.');
                });
                document.addEventListener('admob.interstitial.load_fail', () => {
                    $log.info('admob interstitial load failed.');
                });
                document.addEventListener('admob.interstitial.open', () => {
                    $log.info('admob interstitial opened.');
                });
                document.addEventListener('admob.interstitial.exit_app', () => {
                    $log.info('admob interstitial exit_app.');
                });
            }
        }
    };

    /**
     *
     */
    service.initWithOptions = function (options) {
        // Enable dev mode from backoffice settings
        if (options.isTesting) {
            admob.setDevMode(true);
        }
        service.loadBanner();
        service.prepareInterstitial();
    };

    /**
     * Clear / Reload banner!
     */
    service.loadBanner = function () {
        if (service.options.banner) {
            $log.info('init admob banner');

            if (!service.canReloadBanner) {
                $log.info('admob banner not allowed on this page');
            }

            var optsId = service.currentPlatform === 'ios' ?
                {ios: service.options.banner_id} : {android: service.options.banner_id};

            service.removeBanner();
            service.lastBannerId = service.options.banner_id;
            admob.banner.show({
                id: optsId,
                position: 'bottom',
                size: 'SMART_BANNER'
            });
        } else {
            $log.info('!ko init admob banner');
        }
    };

    /**
     *
     */
    service.removeBanner = function () {
        if (service.options.banner && service.lastBannerId !== null) {

            var optsId = service.currentPlatform === 'ios' ?
                {ios: service.options.banner_id} : {android: service.options.banner_id};

            try {
                admob.banner.hide(optsId);
            } catch (e) {
                $log.error('admob hide banner error: ' + e.message);
            }
        }
    };

    /**
     * Add states that must never load Ads!
     * @param states
     */
    service.forbidStates = function (states) {
        service.forbiddenStates = service.forbiddenStates.concat(states);
    };

    /**
     *
     */
    service.prepareInterstitial = function () {
        if (service.options.interstitial) {
            $log.info('init interstitial banner');

            service.preloadInterstitial();
        } else {
            $log.info('!ko init interstitial banner');
        }
    };

    service.preloadInterstitial = function () {
        var optsId = service.currentPlatform === 'ios' ?
            {ios: service.options.interstitial_id} : {android: service.options.interstitial_id};

        service.interstitialPromise = admob.interstitial.load({
            id: optsId,
        });
    };

    /**
     *
     * @private
     */
    service._reload = function () {
        service.loadBanner();
        // Remove the event listener until next interstitial load!
        document.removeEventListener('admob.interstitial.close', service._reload);
    };

    return service;
});
