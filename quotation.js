class QuotationHandler {
    constructor() {
        this.quotationUrl = 'quotation_handler.php';
    }
    
    async addWindow(windowType, description, area, quantity, totalCost, calculationData = {}) {
        try {
            // Convert and validate dimensions
            const height = parseFloat(calculationData.height) || 0;
            const width = parseFloat(calculationData.width) || 0;
            
            if (height <= 0 || width <= 0) {
                console.error('Invalid dimensions:', {height, width});
                throw new Error('Window dimensions must be positive numbers');
            }

            const formData = new FormData();
            formData.append('action', 'add_item');
            formData.append('description', description);
            formData.append('unit', 'Sft');
            formData.append('area', parseFloat(area) || 0);
            formData.append('rate', area > 0 ? (totalCost / area) : 0);
            formData.append('amount', parseFloat(totalCost) || 0);
            formData.append('window_type', windowType);
            formData.append('quantity', parseInt(quantity) || 1);
            formData.append('client_id', window.currentClientId || 0);
            
            // Add dimensions as direct fields
            formData.append('height', height);
            formData.append('width', width);
            
            // Also include in calculation_data
            const fullCalcData = {
                ...calculationData,
                height: height,
                width: width,
                source: 'addWindow',
                windowType: windowType,
                description: description
            };
            formData.append('calculation_data', JSON.stringify(fullCalcData));

            const response = await fetch(this.quotationUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(errorData?.error || 'Server responded with status ' + response.status);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error adding to quotation:', error);
            return {
                success: false, 
                error: error.message || 'Failed to add to quotation'
            };
        }
    }
    
    viewQuotation() {
        window.location.href = 'quotation.php';
    }
    
    async clearQuotation() {
        try {
            const response = await this._sendRequest({ action: 'clear_items' });
            return response.success;
        } catch (error) {
            console.error('Error clearing quotation:', error);
            return false;
        }
    }
    
    async getClientCalculations(clientId) {
        try {
            const response = await this._sendRequest({
                action: 'get_calculations',
                client_id: clientId
            });
            return response.calculations || [];
        } catch (error) {
            console.error('Error getting calculations:', error);
            return [];
        }
    }
    
    async _sendRequest(data) {
        const formData = new FormData();
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        const response = await fetch(this.quotationUrl, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => null);
            throw new Error(errorData?.error || 'Network response was not ok');
        }
        
        return await response.json();
    }
}

// Create global quotation handler
window.quotationHandler = new QuotationHandler();

/**
 * Creates quotation buttons and adds them to the specified container
 */
function createQuotationButtons(container, windowType, getCalculationData) {
    container.className = 'quotation-buttons';
    
    // Add to Quotation Button
    const addBtn = document.createElement('button');
    addBtn.className = 'btn btn-primary';
    addBtn.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Add to Quotation';
    addBtn.onclick = async () => {
        try {
            addBtn.disabled = true;
            addBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            
            const {area, quantity, totalCost, height, width, unit} = getCalculationData();
            
            // Validate calculation data
            if (!area || area <= 0 || !totalCost || totalCost <= 0) {
                throw new Error('Invalid calculation values');
            }
            
            const result = await quotationHandler.addWindow(
                windowType, 
                `${windowType} Window`, 
                area, 
                quantity, 
                totalCost,
                {
                    height: height,
                    width: width,
                    quantity: quantity,
                    unit: unit || 'ft'
                }
            );
            
            if (result.success) {
                showAlert(`${windowType} Window added to quotation!`, 'success');
            } else {
                throw new Error(result.error || 'Failed to add to quotation');
            }
        } catch (error) {
            console.error('Add to quotation error:', error);
            showAlert(error.message, 'danger');
        } finally {
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Add to Quotation';
        }
    };
    
    // View Quotation Button
    const viewBtn = document.createElement('button');
    viewBtn.className = 'btn btn-success ms-2';
    viewBtn.innerHTML = '<i class="fas fa-file-invoice me-2"></i>View Quotation';
    viewBtn.onclick = () => quotationHandler.viewQuotation();
    
    // Clear Quotation Button
    const clearBtn = document.createElement('button');
    clearBtn.className = 'btn btn-danger ms-2';
    clearBtn.innerHTML = '<i class="fas fa-trash me-2"></i>Clear';
    clearBtn.onclick = async () => {
        if (confirm('Clear all items from quotation?')) {
            try {
                clearBtn.disabled = true;
                clearBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Clearing...';
                
                const success = await quotationHandler.clearQuotation();
                if (success) {
                    showAlert('Quotation cleared!', 'success');
                } else {
                    throw new Error('Failed to clear quotation');
                }
            } catch (error) {
                console.error('Clear quotation error:', error);
                showAlert(error.message, 'danger');
            } finally {
                clearBtn.disabled = false;
                clearBtn.innerHTML = '<i class="fas fa-trash me-2"></i>Clear';
            }
        }
    };
    
    container.appendChild(addBtn);
    container.appendChild(viewBtn);
    container.appendChild(clearBtn);
}

function showAlert(message, type = 'info') {
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert-quotation');
    if (existingAlert) existingAlert.remove();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-quotation alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = '1000';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
}

// Utility function to convert units to feet
function convertToFeet(value, unit) {
    const conversions = {
        'in': 12,
        'cm': 30.48,
        'mm': 304.8,
        'ft': 1
    };
    return value / (conversions[unit] || 1);
}