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
            if (data.status === 'already_checked_in') {
                alertBox.classList.add('info');
                if (formBody) formBody.style.display = 'none';
                submitBtn.style.display = 'none';
                
                alertBox.innerHTML = `
                    <div style="text-align: center; padding: 5px 0;">
                        <div style="font-size: 1.25rem; font-weight: bold; color: #1565c0; margin-bottom: 6px;">
                            ℹ️ Quý khách đã checkin trước đó rồi
                        </div>
                        <div style="font-size: 0.85rem; color: #555; margin-bottom: 15px;">
                            Thời gian ghi nhận: <strong>${data.data.checkin_time}</strong>
                        </div>
                        
                        <div style="background: #ffffff; border: 1px solid #bbdefb; border-radius: 12px; padding: 15px; text-align: left; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                            <div style="margin-bottom: 10px; font-size: 0.95rem; color: #333;">
                                👤 <strong>Họ tên:</strong> ${data.data.full_name}
                            </div>
                            <div style="margin-bottom: 10px; font-size: 0.95rem; color: #333;">
                                📞 <strong>Số điện thoại:</strong> ${data.data.phone}
                            </div>
                            <div style="margin-bottom: 10px; font-size: 0.95rem; color: #d32f2f;">
                                🪑 <strong>Vị trí ngồi:</strong> <span style="background: #fce4e4; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 1rem;">${data.data.table_name}</span>
                            </div>
                            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed #bbdefb; text-align: center;">
                                <div style="font-size: 0.8rem; color: #666; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">🎁 MÃ THAM GIA QUAY THƯỞNG / BỐC THĂM</div>
                                <div style="font-size: 1.5rem; font-weight: 800; color: #d32f2f; letter-spacing: 1px; background: #fff5f5; display: inline-block; padding: 6px 20px; border-radius: 20px; border: 1px solid #ffcdd2;">
                                    ${data.data.lucky_draw_code}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (data.status === 'success') {
                alertBox.classList.add('success');
                alertBox.innerHTML = `<strong>Thành công!</strong> ${data.message}`;
                
                // Ẩn form nhập liệu sau khi thành công
                if (formBody) formBody.style.display = 'none';
                submitBtn.style.display = 'none';
                
                if (data.data && data.data.match_status) {
                    let extraMsg = '';
                    if (data.data.match_status === 'matched') {
                        extraMsg = `<div style="margin-top:10px; padding-top:10px; border-top:1px dashed #4caf50;">
                            Cảm ơn quý khách đã tham dự Hội nghị. Chúc quý vị có một sự kiện tuyệt vời!<br>`;
                        if (data.data.table_name) {
                            extraMsg += `<strong>Vị trí ngồi của quý khách:</strong> ${data.data.table_name}<br>`;
                        }
                        if (data.data.lucky_draw_code) {
                            extraMsg += `<strong>Mã bốc thăm:</strong> ${data.data.lucky_draw_code}<br>`;
                        }
                        extraMsg += `</div>`;
                    } else if (data.data.match_status === 'walk_in') {
                        extraMsg = `<div style="margin-top:10px; padding-top:10px; border-top:1px dashed #4caf50;">
                            Thông tin của quý khách hiện không có trong danh sách của tổ chức. Vui lòng liên hệ với lễ tân để được hỗ trợ!
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
