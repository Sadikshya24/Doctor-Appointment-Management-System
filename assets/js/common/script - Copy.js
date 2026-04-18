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
                }
            });
        });
    }
});
