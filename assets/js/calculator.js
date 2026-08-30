document.addEventListener('DOMContentLoaded', () => {
    const paperSelect = document.getElementById('paper_id');
    const form = document.getElementById('calc-form');
    const calcBtn = document.getElementById('calc-btn');
    const formError = document.getElementById('form-error');
    const qtyError = document.getElementById('qty-error');
    const qtyInput = document.getElementById('quantity');

    let minQuantity = 100;

    // Load paper options + minimum quantity from the API.
    // Nothing about pricing is hardcoded in this file - the calculator
    // only ever displays what the server tells it.
    fetch('../api/rates.php')
        .then(res => res.json())
        .then(data => {
            minQuantity = data.minimum_quantity || 100;
            qtyInput.min = minQuantity;
            qtyInput.placeholder = `Minimum ${minQuantity}`;

            paperSelect.innerHTML = '';
            data.papers.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.paper_size} ${p.paper_quality} (${p.gsm} GSM) - ₹${Number(p.rate_per_sheet).toFixed(2)}/sheet`;
                paperSelect.appendChild(opt);
            });
        })
        .catch(() => {
            paperSelect.innerHTML = '<option value="">Unable to load paper options</option>';
        });

    qtyInput.addEventListener('input', () => {
        const val = parseInt(qtyInput.value, 10);
        qtyError.textContent = (val && val < minQuantity) ? `Minimum quantity is ${minQuantity} sheets.` : '';
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        formError.textContent = '';

        const quantity = parseInt(qtyInput.value, 10);
        if (!quantity || quantity < minQuantity) {
            qtyError.textContent = `Minimum quantity is ${minQuantity} sheets.`;
            return;
        }

        const payload = {
            job_name: document.getElementById('job_name').value.trim(),
            paper_id: parseInt(paperSelect.value, 10),
            quantity: quantity,
            printing_type: document.getElementById('printing_type').value,
            lamination_type: document.getElementById('lamination_type').value,
            save: false,
        };

        calcBtn.disabled = true;
        calcBtn.textContent = 'Calculating…';

        // The server is the source of truth: it re-reads every rate
        // from MySQL and recalculates the whole breakdown itself.
        fetch('../api/calculate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Calculation failed.');
            return data;
        })
        .then(renderBreakdown)
        .catch(err => { formError.textContent = err.message; })
        .finally(() => {
            calcBtn.disabled = false;
            calcBtn.textContent = 'Calculate Price';
        });
    });

    function rupees(n) {
        return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderBreakdown(data) {
        const b = data.breakdown;
        document.getElementById('breakdown-empty').classList.add('hidden');
        document.getElementById('breakdown-table').classList.remove('hidden');

        document.getElementById('b-paper').textContent = rupees(b.paper_cost);
        document.getElementById('b-printing').textContent = rupees(b.printing_cost);
        document.getElementById('b-lamination').textContent = rupees(b.lamination_cost);
        document.getElementById('b-base').textContent = rupees(b.base_cost);
        document.getElementById('b-margin-label').textContent = `Margin (${b.margin_percent}%)`;
        document.getElementById('b-margin').textContent = rupees(b.margin_amount);
        document.getElementById('b-subtotal').textContent = rupees(b.subtotal_after_margin);
        document.getElementById('b-gst-label').textContent = `GST (${b.gst_percent}%)`;
        document.getElementById('b-gst').textContent = rupees(b.gst_amount);
        document.getElementById('b-delivery').textContent = rupees(b.delivery_charge);
        document.getElementById('b-total').textContent = rupees(b.total_before_discount);
        document.getElementById('b-payable').textContent = rupees(b.amount_payable);
    }
});
