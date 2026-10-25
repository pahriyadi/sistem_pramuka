/**
 * Racana Universitas Samawa (UNSA) - Interactive JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. HITUNG UMUR OTOMATIS DARI TANGGAL LAHIR
    const inputTglLahir = document.getElementById('tanggal_lahir');
    const displayUmur = document.getElementById('display_umur');
    const inputUmurTeks = document.getElementById('umur_teks');

    function calculateAge(birthDateString) {
        if (!birthDateString) return null;
        const birthDate = new Date(birthDateString);
        const today = new Date();
        
        if (isNaN(birthDate.getTime())) return null;

        let years = today.getFullYear() - birthDate.getFullYear();
        let months = today.getMonth() - birthDate.getMonth();
        let days = today.getDate() - birthDate.getDate();

        if (days < 0) {
            months--;
        }
        if (months < 0) {
            years--;
            months += 12;
        }

        return { years, months };
    }

    if (inputTglLahir && displayUmur) {
        function updateAgeDisplay() {
            const val = inputTglLahir.value;
            if (val) {
                const age = calculateAge(val);
                if (age && age.years >= 0) {
                    displayUmur.innerHTML = `<span class="badge bg-success py-2 px-3"><i class="fa-solid fa-cake-candles me-1"></i> ${age.years} Tahun ${age.months} Bulan</span>`;
                    if (inputUmurTeks) inputUmurTeks.value = `${age.years} Tahun ${age.months} Bulan`;
                } else {
                    displayUmur.innerHTML = `<span class="badge bg-danger py-2 px-3">Tanggal tidak valid</span>`;
                }
            } else {
                displayUmur.innerHTML = `<span class="text-muted small">Pilih tanggal lahir</span>`;
            }
        }

        inputTglLahir.addEventListener('change', updateAgeDisplay);
        inputTglLahir.addEventListener('input', updateAgeDisplay);
        // Initial run if editing existing participant
        if (inputTglLahir.value) {
            updateAgeDisplay();
        }
    }

    // 2. AUTOFILL JENIS KELAMIN BERDASARKAN KATEGORI PESERTA
    const selectKategori = document.getElementById('kategori_id');
    const radioGenderL = document.getElementById('gender_l');
    const radioGenderP = document.getElementById('gender_p');
    const selectGender = document.getElementById('jenis_kelamin');

    if (selectKategori) {
        selectKategori.addEventListener('change', function() {
            const selectedOption = selectKategori.options[selectKategori.selectedIndex];
            const defaultGender = selectedOption.getAttribute('data-gender'); // 'L' or 'P'

            if (defaultGender === 'L') {
                if (radioGenderL) radioGenderL.checked = true;
                if (selectGender) selectGender.value = 'L';
            } else if (defaultGender === 'P') {
                if (radioGenderP) radioGenderP.checked = true;
                if (selectGender) selectGender.value = 'P';
            }
        });
    }

    // 3. FOTO PREVIEW UNTUK ID CARD PESERTA
    const inputFoto = document.getElementById('foto_peserta');
    const previewContainer = document.getElementById('preview_foto');

    if (inputFoto && previewContainer) {
        inputFoto.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                if (file.size > 3 * 1024 * 1024) {
                    alert('Ukuran foto terlalu besar! Maksimal 3 MB.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Preview Foto" class="img-fluid rounded" style="width:100%; height:100%; object-fit:cover;">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 4. MATA LOMBA MULTI-SELECT CHECKBOX COUNTER
    const checkboxesLomba = document.querySelectorAll('.checkbox-lomba');
    const countBadge = document.getElementById('selected_lomba_count');

    if (checkboxesLomba.length > 0 && countBadge) {
        function updateLombaCount() {
            let count = 0;
            checkboxesLomba.forEach(cb => {
                if (cb.checked) count++;
            });
            countBadge.textContent = `${count} Mata Lomba Dipilih`;
        }

        checkboxesLomba.forEach(cb => {
            cb.addEventListener('change', updateLombaCount);
        });
        updateLombaCount();
    }

    // 5. SIDEBAR MOBILE TOGGLE
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar-wrapper');
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
});
