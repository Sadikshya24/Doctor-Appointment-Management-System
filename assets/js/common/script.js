document.addEventListener('DOMContentLoaded', () => {
    // Tab switching logic
    const tabs = document.querySelectorAll('.auth-tab');
    const formContainers = document.querySelectorAll('.auth-form-container');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active from all tabs & forms
            tabs.forEach(t => t.classList.remove('active'));
            formContainers.forEach(fc => fc.classList.remove('active'));

            // Add active to clicked tab & matching form
            tab.classList.add('active');
            const targetId = tab.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Toggle doctor fields on signup
    const roleRadios = document.querySelectorAll('#signup input[name="role"]');
    const doctorFields = document.getElementById('doctor-fields');
    const nmcInput = document.getElementById('nmc_number');
    const cvInput = document.getElementById('cv_file');
    const hospitalInput = document.getElementById('hospital_id');

    if (roleRadios && doctorFields) {
        roleRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'doctor') {
                    doctorFields.style.animation = 'fadeIn 0.3s forwards';
                    doctorFields.style.display = 'block';
                    nmcInput.setAttribute('required', 'true');
                    cvInput.setAttribute('required', 'true');
                    hospitalInput.setAttribute('required', 'true');
                } else {
                    doctorFields.style.display = 'none';
                    nmcInput.removeAttribute('required');
                    cvInput.removeAttribute('required');
                    hospitalInput.removeAttribute('required');
                    
                    // Clear errors when hiding
                    [nmcInput, cvInput, hospitalInput].forEach(input => {
                        const evt = new Event('input');
                        input.dispatchEvent(evt);
                    });
                }
            });
        });
    }

    // Real-time Form Validation
    const authForms = document.querySelectorAll('.auth-form-container form');
    
    authForms.forEach(form => {
        const inputs = form.querySelectorAll('input:not([type="hidden"]):not([type="radio"]), select');
        
        inputs.forEach(input => {
            // Create error message element if not exists and field is inside input-field
            const inputField = input.closest('.input-field');
            if (inputField && !inputField.nextElementSibling?.classList.contains('input-error-msg')) {
                const errorSpan = document.createElement('div');
                errorSpan.className = 'input-error-msg';
                inputField.parentNode.insertBefore(errorSpan, inputField.nextSibling);
            }

            const validateInput = () => {
                const inputField = input.closest('.input-field');
                if (!inputField) return;
                
                const errorSpan = inputField.nextElementSibling;
                if (!errorSpan || !errorSpan.classList.contains('input-error-msg')) return;

                if (!input.checkValidity() && (input.required || input.value.trim() !== '')) {
                    inputField.classList.add('has-error');
                    inputField.classList.remove('has-success');
                    errorSpan.style.display = 'block';
                    
                    if (input.validity.valueMissing) {
                        errorSpan.textContent = 'This field is required.';
                    } else if (input.validity.patternMismatch) {
                        errorSpan.textContent = input.title || `Please enter a valid format.`;
                    } else if (input.validity.typeMismatch) {
                        if (input.type === 'email') errorSpan.textContent = 'Please enter a valid email address.';
                        else errorSpan.textContent = `Please enter a valid value.`;
                    } else {
                        errorSpan.textContent = 'Invalid value.';
                    }
                } else if (input.checkValidity() && input.value.trim() !== '') {
                    inputField.classList.remove('has-error');
                    inputField.classList.add('has-success');
                    errorSpan.style.display = 'none';
                    errorSpan.textContent = '';
                } else {
                    // Empty and not required (or hidden)
                    inputField.classList.remove('has-error', 'has-success');
                    errorSpan.style.display = 'none';
                    errorSpan.textContent = '';
                }
            };

            input.addEventListener('input', validateInput);
            input.addEventListener('change', validateInput); // specific for select/file
            input.addEventListener('blur', validateInput);
        });

        // Trigger validation on submit to ensure errors show
        form.addEventListener('submit', (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                inputs.forEach(input => {
                    input.dispatchEvent(new Event('input'));
                });
            }
        });
    });
});
