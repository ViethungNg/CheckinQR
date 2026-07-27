document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkin-form');
    const submitBtn = document.getElementById('btn-submit');
    const spinner = document.getElementById('spinner');
    const btnText = document.getElementById('btn-text');
    const alertBox = document.getElementById('alert-message');
    const resultBox = document.getElementById('result-box');
    const formBody = document.getElementById('form-fields');

    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Reset trạng thái
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
        alertBox.innerHTML = '';
        
        // Khóa nút
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        btnText.textContent = 'Đang xử lý...';

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            alertBox.style.display = 'block';
            if (data.status === 'success') {
                alertBox.classList.add('success');
                alertBox.innerHTML = `<strong>Thành công!</strong> ${data.message}`;
                
                // Ẩn form nhập liệu sau khi thành công
                if (formBody) formBody.style.display = 'none';
                submitBtn.style.display = 'none';
                
                if (data.data && data.data.match_status) {
                    let extraMsg = '';
                    if (data.data.match_status === 'matched') {
                        extraMsg = `<div style="margin-top:10px; padding-top:10px; border-top:1px dashed #4caf50;">
                            Cảm ơn bạn đã đến tham dự. Chúc bạn có một sự kiện tuyệt vời!<br>`;
                        if (data.data.table_name) {
                            extraMsg += `<strong>Vị trí ngồi của bạn:</strong> ${data.data.table_name}<br>`;
                        }
                        if (data.data.lucky_draw_code) {
                            extraMsg += `<strong>Mã bốc thăm:</strong> ${data.data.lucky_draw_code}<br>`;
                        }
                        extraMsg += `</div>`;
                    } else if (data.data.match_status === 'walk_in') {
                        extraMsg = `<div style="margin-top:10px; padding-top:10px; border-top:1px dashed #4caf50;">
                            Thông tin của bạn đã được ghi nhận. Vui lòng đợi lễ tân hỗ trợ trong giây lát.
                        </div>`;
                    }
                    alertBox.innerHTML += extraMsg;
                }
            } else {
                alertBox.classList.add('error');
                alertBox.innerHTML = `<strong>Lỗi:</strong> ${data.message}`;
                // Mở khóa nút để thử lại
                submitBtn.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = 'Xác nhận Check-in';
            }
            
        } catch (error) {
            alertBox.style.display = 'block';
            alertBox.classList.add('error');
            alertBox.innerHTML = '<strong>Lỗi:</strong> Không thể kết nối đến máy chủ. Vui lòng thử lại!';
            
            // Mở khóa nút
            submitBtn.disabled = false;
            spinner.style.display = 'none';
            btnText.textContent = 'Xác nhận Check-in';
        }
    });
});
