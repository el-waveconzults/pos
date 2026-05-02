<div class="calculator-widget">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-calculator text-primary"></i> Calculator</h5>
                </div>
                <div class="card-body p-3">
                    <div class="calculator">
                        <input type="text" id="calcDisplay" class="form-control form-control-lg text-end mb-3" readonly placeholder="0">

                        <div class="row g-2 mb-2">
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('7')">7</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('8')">8</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('9')">9</button></div>
                            <div class="col-3"><button class="btn btn-warning w-100 py-3" onclick="calcInput('/')">÷</button></div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('4')">4</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('5')">5</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('6')">6</button></div>
                            <div class="col-3"><button class="btn btn-warning w-100 py-3" onclick="calcInput('*')">×</button></div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('1')">1</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('2')">2</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('3')">3</button></div>
                            <div class="col-3"><button class="btn btn-warning w-100 py-3" onclick="calcInput('-')">−</button></div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('0')">0</button></div>
                            <div class="col-3"><button class="btn btn-light w-100 py-3" onclick="calcInput('.')">.</button></div>
                            <div class="col-3"><button class="btn btn-danger w-100 py-3" onclick="calcClear()">C</button></div>
                            <div class="col-3"><button class="btn btn-warning w-100 py-3" onclick="calcInput('+')">+</button></div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6"><button class="btn btn-info w-100 py-3" onclick="calcInput('%')">%</button></div>
                            <div class="col-6"><button class="btn btn-success w-100 py-3" onclick="calcEqual()">=</button></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .calculator button {
        font-size: 20px;
        font-weight: 600;
        border-radius: 10px;
    }

    .calculator .form-control-lg {
        font-size: 28px;
        font-weight: 700;
        background: #f8f9fa;
        border: 2px solid #e9ecef;
    }
</style>

<script>
    let calcValue = '';
    let calcResult = 0;
    let calcOperator = '';
    let newNumber = true;

    function calcInput(val) {
        const display = document.getElementById('calcDisplay');

        if (newNumber) {
            calcValue = val;
            newNumber = false;
        } else {
            calcValue += val;
        }
        display.value = calcValue;
    }

    function calcEqual() {
        const display = document.getElementById('calcDisplay');
        try {
            // Safe evaluation - only allow numbers and operators
            let expression = calcValue.replace(/[^0-9.+*/%-]/g, '');
            calcResult = eval(expression);
            display.value = calcResult;
            calcValue = calcResult.toString();
            newNumber = true;
        } catch (e) {
            display.value = 'Error';
            calcValue = '';
            newNumber = true;
        }
    }

    function calcClear() {
        const display = document.getElementById('calcDisplay');
        calcValue = '';
        calcResult = 0;
        newNumber = true;
        display.value = '0';
    }

    // Keyboard support
    document.addEventListener('keydown', function(e) {
        const key = e.key;
        if (key >= '0' && key <= '9') calcInput(key);
        else if (key === '.') calcInput('.');
        else if (key === '+') calcInput('+');
        else if (key === '-') calcInput('-');
        else if (key === '*') calcInput('*');
        else if (key === '/') calcInput('/');
        else if (key === 'Enter') calcEqual();
        else if (key === 'Escape') calcClear();
        else if (key === '%') calcInput('%');
    });
</script>