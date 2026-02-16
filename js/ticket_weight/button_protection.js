            // Button Protection System for Ticket Weights
            class TicketWeightsButtonProtection {
                constructor() {
                    this.init();
                }

                init() {
                    this.protectAddWeightButton();
                    this.protectSaveTransactionButton();
                    this.protectSaveChangesButton();
                    this.protectGenerateInvoiceButton();
                    this.protectGenerateCombinedInvoiceButton();
                    this.protectSearchButtons();
                    this.protectManageTransactionsButtons();
                    this.protectEditWeightButtons();
                    this.protectDeleteWeightButtons();
                    this.protectSelectTicketButtons();
                }

                protectButton(button, loadingText = 'Processing...', duration = 2000, disableButton = true) {
                    if (button && (!button.disabled || !disableButton)) {
                        const originalText = button.innerHTML;
                        if (disableButton) {
                            button.disabled = true;
                            button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${loadingText}`;
                        }

                        setTimeout(() => {
                            if (disableButton) {
                                button.disabled = false;
                                button.innerHTML = originalText;
                            }
                        }, duration);
                    }
                }

                protectAddWeightButton() {
                    const button = document.querySelector('[data-target="#addTransactionModal"]');
                    if (button) {
                        button.addEventListener('click', () => {
                            this.protectButton(button, 'Loading...', 1000, false);
                        });
                    }
                }

                protectSaveTransactionButton() {
                    const form = document.getElementById('addTransactionForm');
                    const button = document.getElementById('saveTransactionBtn');
                    if (form && button) {
                        form.addEventListener('submit', () => {
                            this.protectButton(button, 'Saving Transaction...', 3000);
                        });
                    }
                }

                protectSaveChangesButton() {
                    const form = document.getElementById('editWeightForm');
                    const button = document.querySelector('#editWeightForm button[type="submit"]');
                    if (form && button) {
                        form.addEventListener('submit', () => {
                            this.protectButton(button, 'Saving Changes...', 3000);
                        });
                    }
                }

                protectGenerateInvoiceButton() {
                    const button = document.getElementById('generateInvoiceBtn');
                    if (button) {
                        button.addEventListener('click', () => {
                            this.protectButton(button, 'Generating Invoice...', 5000);
                        });
                    }
                }

                protectGenerateCombinedInvoiceButton() {
                    const button = document.getElementById('generateCombinedWeightInvoice');
                    if (button) {
                        button.addEventListener('click', () => {
                            this.protectButton(button, 'Generating Invoice...', 5000);
                        });
                    }
                }

                protectSearchButtons() {
                    const searchPNRBtn = document.getElementById('searchPNRBtn');
                    const searchPassengerBtn = document.getElementById('searchPassengerBtn');

                    if (searchPNRBtn) {
                        searchPNRBtn.addEventListener('click', () => {
                            this.protectButton(searchPNRBtn, 'Searching...', 2000);
                        });
                    }

                    if (searchPassengerBtn) {
                        searchPassengerBtn.addEventListener('click', () => {
                            this.protectButton(searchPassengerBtn, 'Searching...', 2000);
                        });
                    }
                }

                protectManageTransactionsButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('[onclick*="manageTransactions"]')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Loading...', 1000);
                            }
                        }
                    });
                }

                protectEditWeightButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('[onclick*="editWeight"]')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Loading...', 1000);
                            }
                        }
                    });
                }

                protectDeleteWeightButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('[onclick*="deleteWeight"]')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Deleting...', 2000);
                            }
                        }
                    });
                }

                protectSelectTicketButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('.select-ticket')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Loading...', 1000);
                            }
                        }
                    });
                }
            }

            // Initialize button protection
            const ticketWeightsButtonProtection = new TicketWeightsButtonProtection();
