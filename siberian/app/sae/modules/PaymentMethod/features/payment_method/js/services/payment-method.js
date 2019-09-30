/**
 * PaymentMethod service
 */
angular
.module("starter")
.service("PaymentMethod", function (Modal, $pwaRequest) {
    var service = {
        modal: null
    };

    service.onStart = function () {};

    service.openModal = function ($scope, options) {
        Modal.fromTemplateUrl("./features/payment_method/assets/templates/l1/payment-modal.html", {
            scope: angular.extend($scope, {
                options: options
            })
        }).then(function(modal) {
            service.modal = modal;
            service.modal.show();
        });
    };

    service.closeModal = function () {
        service.modal.hide();
    };

    service.fetchGateways = function () {
        return $pwaRequest.get("/paymentmethod/mobile_gateway/fetch-all");
    };

    return service;
});