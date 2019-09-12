/**
 * Directive payment-stripe-form
 */
angular
.module("starter")
.directive("paymentStripeCards", function () {
    return {
        restrict: "E",
        replace: true,
        scope: {
            cards: "=?",
            cardsEndpoint: "=?",
            lineAction: "=?",
            actions: "=?"
        },
        templateUrl: "features/payment_stripe/assets/templates/l1/payment-stripe-cards.html",
        compile: function(element, attrs){
            if (!attrs.actions) {
                attrs.actions = [];
            }

            if (!attrs.lineAction) {
                attrs.lineAction = null;
            }

            if (!attrs.cards) {
                attrs.cards = null;
            }

            if (!attrs.cardsEndpoint) {
                attrs.cardsEndpoint = null;
            }

            if (!attrs.cards && !attrs.cardsEndpoint) {
               throw new Error("Directive paymentStripeCards: `cards` or `cardsEndpoint` is required.");
            }
        },
        controller: function ($scope, $rootScope, $pwaRequest, Dialog) {
            $scope.fetchCards = function () {
                $pwaRequest
                .get($scope.cardsEndpoint, {
                    cache: false
                }).then(function (payload) {
                    $scope.cards = payload.cards;
                }, function (error) {
                    Dialog.alert("Error", error.message, "OK", -1, "payment_stripe");
                });
            };

            $scope.lineActionTrigger = function (card) {
                if (typeof $scope.lineAction === "function") {
                    $scope.lineAction(card);
                }
            };

            $scope.actionCallback = function (action, card) {
                if (typeof action.callback === "function") {
                    action.callback(card);
                }
            };

            $scope.brand = function (brand) {
                switch (brand.toLowerCase()) {
                    case "visa":
                        return "./features/payment_stripe/assets/templates/images/003-cc-visa.svg";
                    case "mastercard":
                        return "./features/payment_stripe/assets/templates/images/004-cc-mastercard.svg";
                    case "american express":
                        return "./features/payment_stripe/assets/templates/images/005-cc-amex.png";
                }
                return "./features/payment_stripe/assets/templates/images/006-cc.svg";
            };

            if ($scope.cardsEndpoint !== null) {
                $scope.fetchCards();
            }

            $rootScope.$on("paymentStripeCards.refresh", function () {
                $scope.fetchCards();
            });
        }
    };
});