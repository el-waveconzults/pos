<?php
require_once 'config/config.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-calculator me-2"></i>Calculator
                </h4>
            </div>
            <div class="card-body">
                <!-- Demo Notice -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Demo Mode:</strong> This is a demonstration calculator for quick calculations during sales.
                </div>

                <!-- Calculator Display -->
                <div class="calculator-container">
                    <div class="calculator-display mb-3">
                        <input type="text" id="calcDisplay" class="form-control form-control-lg text-end" value="0" readonly>
                    </div>

                    <!-- Calculator Buttons -->
                    <div class="calculator-buttons">
                        <div class="row g-2">
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="7">7</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="8">8</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="9">9</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-warning btn-lg w-100 calc-btn" data-value="/">÷</button>
                            </div>

                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="4">4</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="5">5</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="6">6</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-warning btn-lg w-100 calc-btn" data-value="*">×</button>
                            </div>

                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="1">1</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="2">2</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="3">3</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-warning btn-lg w-100 calc-btn" data-value="-">-</button>
                            </div>

                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value="0">0</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-secondary btn-lg w-100 calc-btn" data-value=".">.</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-success btn-lg w-100 calc-btn" data-value="=">=</button>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-warning btn-lg w-100 calc-btn" data-value="+">+</button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-6">
                                <button class="btn btn-danger btn-lg w-100" id="clearBtn">Clear</button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-info btn-lg w-100" id="backspaceBtn">
                                    <i class="fas fa-backspace"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Calculations -->
                <div class="mt-4">
                    <h5>Quick Calculations</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Tax Calculator (7.5% VAT)</h6>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">₦</span>
                                        <input type="number" class="form-control" id="taxAmount" placeholder="Enter amount">
                                    </div>
                                    <button class="btn btn-primary btn-sm" onclick="calculateTax()">Calculate Tax</button>
                                    <div id="taxResult" class="mt-2 fw-bold text-success"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Discount Calculator</h6>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">₦</span>
                                        <input type="number" class="form-control" id="discountAmount" placeholder="Enter amount">
                                    </div>
                                    <div class="input-group mb-2">
                                        <input type="number" class="form-control" id="discountPercent" placeholder="Discount %">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <button class="btn btn-primary btn-sm" onclick="calculateDiscount()">Calculate Discount</button>
                                    <div id="discountResult" class="mt-2 fw-bold text-success"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .calculator-container {
        max-width: 400px;
        margin: 0 auto;
    }

    .calculator-display input {
        font-size: 2rem;
        font-weight: bold;
        background: #f8f9fa;
        border: 2px solid #dee2e6;
    }

    .calculator-buttons .btn {
        font-size: 1.2rem;
        font-weight: bold;
        height: 60px;
    }

    .calc-btn:hover {
        transform: scale(1.05);
        transition: transform 0.1s;
    }
</style>

<script>
    // Calculator functionality
    let display = document.getElementById('calcDisplay');
    let currentInput = '0';
    let operator = '';
    let previousInput = '';

    document.querySelectorAll('.calc-btn').forEach(button => {
        button.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            handleCalculatorInput(value);
        });
    });

    document.getElementById('clearBtn').addEventListener('click', function() {
        clearCalculator();
    });

    document.getElementById('backspaceBtn').addEventListener('click', function() {
        backspace();
    });

    function handleCalculatorInput(value) {
        if (value >= '0' && value <= '9' || value === '.') {
            if (currentInput === '0' || currentInput === 'Error') {
                currentInput = value;
            } else {
                currentInput += value;
            }
        } else if (value === '+' || value === '-' || value === '*' || value === '/') {
            if (previousInput !== '') {
                calculate();
            }
            operator = value;
            previousInput = currentInput;
            currentInput = '0';
        } else if (value === '=') {
            calculate();
        }

        updateDisplay();
    }

    function calculate() {
        try {
            const prev = parseFloat(previousInput);
            const current = parseFloat(currentInput);

            switch (operator) {
                case '+':
                    currentInput = (prev + current).toString();
                    break;
                case '-':
                    currentInput = (prev - current).toString();
                    break;
                case '*':
                    currentInput = (prev * current).toString();
                    break;
                case '/':
                    if (current === 0) {
                        currentInput = 'Error';
                    } else {
                        currentInput = (prev / current).toString();
                    }
                    break;
            }

            operator = '';
            previousInput = '';
        } catch (e) {
            currentInput = 'Error';
        }
    }

    function clearCalculator() {
        currentInput = '0';
        operator = '';
        previousInput = '';
        updateDisplay();
    }

    function backspace() {
        if (currentInput.length > 1) {
            currentInput = currentInput.slice(0, -1);
        } else {
            currentInput = '0';
        }
        updateDisplay();
    }

    function updateDisplay() {
        display.value = currentInput;
    }

    // Tax calculation
    function calculateTax() {
        const amount = parseFloat(document.getElementById('taxAmount').value);
        if (isNaN(amount)) {
            document.getElementById('taxResult').textContent = 'Please enter a valid amount';
            return;
        }

        const tax = amount * 0.075; // 7.5% VAT
        const total = amount + tax;

        document.getElementById('taxResult').innerHTML =
            `Tax: ₦${tax.toLocaleString()}<br>Total: ₦${total.toLocaleString()}`;
    }

    // Discount calculation
    function calculateDiscount() {
        const amount = parseFloat(document.getElementById('discountAmount').value);
        const percent = parseFloat(document.getElementById('discountPercent').value);

        if (isNaN(amount) || isNaN(percent)) {
            document.getElementById('discountResult').textContent = 'Please enter valid values';
            return;
        }

        const discount = amount * (percent / 100);
        const final = amount - discount;

        document.getElementById('discountResult').innerHTML =
            `Discount: ₦${discount.toLocaleString()}<br>Final: ₦${final.toLocaleString()}`;
    }
</script>