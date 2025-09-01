// add_quotation.js
export function handleAddToQuotation(fetchDataCallback) {
    const addBtn = document.getElementById('addToQuotationBtn');
    if (!addBtn || typeof fetchDataCallback !== 'function') return;

    addBtn.addEventListener('click', () => {
        const data = fetchDataCallback(); // From calculator-specific logic
        const { client_id, company_id, fullData, totalCost, area } = data;

        // 1. Send data to quotation_handler.php
        const quoteFormData = new FormData();
        quoteFormData.append('action', 'add_item');
        quoteFormData.append('window_type', '2PSL'); // You can pass this dynamically
        quoteFormData.append('description', '2PSL Window'); // You can pass this too
        quoteFormData.append('area', area);
        quoteFormData.append('rate', totalCost / area);
        quoteFormData.append('amount', totalCost);
        quoteFormData.append('quantity', fullData.dimensions.quantity);
        quoteFormData.append('height', fullData.dimensions.height);
        quoteFormData.append('width', fullData.dimensions.width);
        quoteFormData.append('client_id', client_id);
        quoteFormData.append('calculation_data', JSON.stringify(fullData));

        fetch('quotation_handler.php', {
            method: 'POST',
            body: quoteFormData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Save to save_window_calculation.php
                const saveFormData = new FormData();
                saveFormData.append('action', 'save_calculation');
                saveFormData.append('client_id', client_id);
                saveFormData.append('company_id', company_id);
                saveFormData.append('window_type', '2PSL'); // Also replace dynamically
                saveFormData.append('height', fullData.dimensions.height);
                saveFormData.append('width', fullData.dimensions.width);
                saveFormData.append('quantity', fullData.dimensions.quantity);
                saveFormData.append('total_area', fullData.dimensions.area);
                saveFormData.append('material_cost', fullData.totals.materials);
                saveFormData.append('hardware_cost', fullData.totals.hardware);
                saveFormData.append('glass_cost', fullData.totals.glass);
                saveFormData.append('total_cost', fullData.totals.grandTotal);

                // Append all other needed fields dynamically from fullData if required

                return fetch('./Pages/save_window_calculation.php', {
                    method: 'POST',
                    body: saveFormData
                });
            } else {
                throw new Error(data.error || 'Add to quotation failed');
            }
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert("Saved and added successfully!");
                setTimeout(() => {
                    const url = new URL(window.location.href);
                    window.location.href = url.toString(); // reload same page with params
                }, 1500);
            } else {
                alert("Save failed: " + (result.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error("Quotation Error:", error);
            alert("Error: " + error.message);
        });
    });
}
