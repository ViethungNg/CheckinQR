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
            if (data.status === 'require_confirmation') {
                alertBox.classList.add('warning');
                submitBtn.style.display = 'none';
                
                alertBox.innerHTML = `
                    <div style="background: #ffffff; border: 2px solid #8b5cf6; border-radius: 14px; padding: 20px; text-align: left; box-shadow: 0 10px 25px rgba(139,92,246,0.15); margin-top: 5px;">
                        <div style="font-size: 1.05rem; font-weight: 800; color: #6d28d9; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>🎟️</span> Xác Nhận Thông Tin Mã Dự Thưởng (${data.data.lucky_draw_code})
                        </div>
                        
                        <p style="font-size: 0.92rem; color: #4b5563; margin-bottom: 12px; line-height: 1.5;">
                            Hệ thống ghi nhận Mã dự thưởng <strong>${data.data.lucky_draw_code}</strong> thuộc danh sách mời của BTC:
                        </p>

                        <div style="background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 10px; padding: 14px; margin-bottom: 14px;">
                            <div style="font-weight: 700; color: #5b21b6; font-size: 0.95rem; margin-bottom: 4px;">👤 Khách BTC mời: ${data.data.original_name}</div>
                            <div style="font-size: 0.88rem; color: #6d28d9; margin-bottom: 4px;">📱 SĐT dự kiến: ${data.data.original_phone_masked}</div>
                            <div style="font-size: 0.88rem; color: #047857; font-weight: 700;">📍 Vị trí bàn: ${data.data.table_name}</div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Thông tin người quét check-in:</div>
                            <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem; margin-top: 2px;">${data.data.entered_name} (${data.data.entered_phone})</div>
                        </div>

                        <p style="font-size: 0.92rem; font-weight: 800; color: #1e293b; margin-bottom: 14px; text-align: center;">
                            Bạn có phải là người đại diện hoặc được ủy quyền tham dự theo mã vé này không?
                        </p>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" id="btn-confirm-yes" class="btn" style="flex: 1; min-width: 140px; background: #10b981; border: none; padding: 12px; border-radius: 10px; font-weight: 800; cursor: pointer; color: white; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                                🟢 Đúng, Tôi là người đại diện
                            </button>
                            <button type="button" id="btn-confirm-no" class="btn" style="flex: 1; min-width: 140px; background: #ef4444; border: none; padding: 12px; border-radius: 10px; font-weight: 800; cursor: pointer; color: white; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(239,68,68,0.3);">
                                🔴 Không phải / Sai thông tin
                            </button>
                        </div>
                    </div>
                `;

                document.getElementById('btn-confirm-yes').addEventListener('click', () => submitConfirmationChoice('confirm'));
                document.getElementById('btn-confirm-no').addEventListener('click', () => submitConfirmationChoice('reject'));
            } else if (data.status === 'already_checked_in') {
                alertBox.classList.add('info');
                if (formBody) formBody.style.display = 'none';
                alertBox.innerHTML = `<strong>Thông báo:</strong> Quý khách đã check-in trước đó!`;

                // Ẩn form nhập liệu khi đã checkin
                if (formBody) formBody.style.display = 'none';
                submitBtn.style.display = 'none';

                if (data.data) {
                    let extraMsg = `
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #90caf9; text-align: center;">
                            <div style="font-size: 0.88rem; color: #1565c0; margin-bottom: 12px;">
                                Thời gian ghi nhận: <strong>${data.data.checkin_time}</strong>
                            </div>
                            
                            <div style="background: #ffffff; border: 1px solid #bbdefb; border-radius: 12px; padding: 15px; text-align: left; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                <div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">
                                    <strong>Họ tên:</strong> ${data.data.full_name}
                                </div>
                                <div style="margin-bottom: 14px; font-size: 0.95rem; color: #333;">
                                    <strong>Số điện thoại:</strong> ${data.data.phone}
                                </div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 10px;">
                                    ${data.data.table_name ? `
                                        <div style="flex: 1; min-width: 140px; background: #e8f5e9; border: 2px solid #66bb6a; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(46,125,50,0.12);">
                                            <div style="font-size: 0.75rem; color: #2e7d32; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Vị trí ngồi</div>
                                            <div style="font-size: 1.25rem; font-weight: 800; color: #1b5e20;">${data.data.table_name}</div>
                                        </div>
                                    ` : ''}
                                    
                                    ${data.data.lucky_draw_code ? `
                                        <div style="flex: 1; min-width: 140px; background: #f3e5f5; border: 2px solid #ab47bc; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(123,31,162,0.12);">
                                            <div style="font-size: 0.75rem; color: #7b1fa2; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Mã bốc thăm</div>
                                            <div style="font-size: 1.25rem; font-weight: 800; color: #4a148c;">${data.data.lucky_draw_code}</div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    alertBox.innerHTML += extraMsg;
                }
            } else if (data.status === 'success') {
                // Ẩn form nhập liệu
                if (formBody) formBody.style.display = 'none';
                submitBtn.style.display = 'none';

                if (data.data && data.data.match_status === 'walk_in') {
                    alertBox.classList.remove('success');
                    alertBox.classList.add('error');
                    alertBox.style.background = '#fff5f5';
                    alertBox.style.border = '2px solid #ef5350';
                    alertBox.style.color = '#c62828';

                    alertBox.innerHTML = `
                        <div style="text-align: center; margin-bottom: 8px;">
                            <strong style="font-size: 1.15rem; color: #c62828;">Chưa có trong danh sách chuẩn bị!</strong>
                        </div>
                        <div style="font-size: 0.95rem; color: #b71c1c; text-align: center; margin-bottom: 14px; line-height: 1.4;">
                            Thông tin của quý khách chưa có trong danh sách chuẩn bị trước. Vui lòng liên hệ lễ tân để được hỗ trợ!
                        </div>

                        <div style="background: #ffffff; border: 1px solid #ffcdd2; border-radius: 12px; padding: 14px; text-align: left; box-shadow: 0 4px 12px rgba(239,83,80,0.08);">
                            <div style="font-size: 0.8rem; color: #c62828; margin-bottom: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                                THÔNG TIN VỪA NHẬP (LIÊN HỆ LỄ TÂN HỖ TRỢ)
                            </div>
                            <div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">
                                <strong>Họ tên:</strong> ${data.data.full_name || ''}
                            </div>
                            <div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">
                                <strong>Số điện thoại:</strong> ${data.data.phone || ''}
                            </div>
                            ${data.data.address ? `<div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;"><strong>Địa chỉ:</strong> ${data.data.address}</div>` : ''}
                            ${data.data.lucky_draw_code ? `<div style="margin-bottom: 8px; font-size: 0.95rem; color: #d32f2f;"><strong>Mã dự thưởng:</strong> ${data.data.lucky_draw_code}</div>` : ''}
                        </div>
                    `;
                } else {
                    alertBox.classList.add('success');
                    alertBox.innerHTML = `<strong>Thành công!</strong> ${data.message}`;

                    if (data.data && data.data.match_status === 'matched') {
                        let extraMsg = `
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #81c784; text-align: center;">
                                <div style="font-size: 0.95rem; color: #2e7d32; font-weight: 600; margin-bottom: 12px; line-height: 1.5;">
                                    Sự hiện diện của Quý khách là niềm vinh hạnh của chúng tôi. Chúc Quý khách có một buổi hội nghị tràn đầy năng lượng và thành công!
                                </div>
                                
                                <div style="background: #ffffff; border: 1px solid #c8e6c9; border-radius: 12px; padding: 12px 16px; text-align: left; margin-bottom: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.03);">
                                    <div style="margin-bottom: 6px; font-size: 0.95rem; color: #1b5e20;">
                                        <strong>Họ tên:</strong> ${data.data.full_name || ''}
                                    </div>
                                    <div style="margin-bottom: 6px; font-size: 0.95rem; color: #1b5e20;">
                                        <strong>Số điện thoại:</strong> ${data.data.phone || ''}
                                    </div>
                                    ${data.data.address ? `
                                        <div style="font-size: 0.95rem; color: #1b5e20;">
                                            <strong>Địa chỉ / Cửa hàng:</strong> ${data.data.address}
                                        </div>
                                    ` : ''}
                                </div>

                                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 10px;">
                                    ${data.data.table_name ? `
                                        <div style="flex: 1; min-width: 140px; background: #e8f5e9; border: 2px solid #66bb6a; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(46,125,50,0.12);">
                                            <div style="font-size: 0.75rem; color: #2e7d32; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Vị trí ngồi</div>
                                            <div style="font-size: 1.25rem; font-weight: 800; color: #1b5e20;">${data.data.table_name}</div>
                                        </div>
                                    ` : ''}
                                    
                                    ${data.data.lucky_draw_code ? `
                                        <div style="flex: 1; min-width: 140px; background: #f3e5f5; border: 2px solid #ab47bc; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(123,31,162,0.12);">
                                            <div style="font-size: 0.75rem; color: #7b1fa2; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Mã bốc thăm</div>
                                            <div style="font-size: 1.25rem; font-weight: 800; color: #4a148c;">${data.data.lucky_draw_code}</div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                        alertBox.innerHTML += extraMsg;
                    }
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

function submitConfirmationChoice(action) {
    const form = document.getElementById('checkin-form');
    if (!form) return;
    
    let hiddenInput = document.getElementById('confirm_code_action');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'confirm_code_action';
        hiddenInput.name = 'confirm_code_action';
        form.appendChild(hiddenInput);
    }
    hiddenInput.value = action;
    
    const submitBtn = document.getElementById('btn-submit');
    if (submitBtn) submitBtn.disabled = false;
    
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
}

// Phím tắt Y / N hỗ trợ xác nhận siêu nhanh
document.addEventListener('keydown', (e) => {
    const yesBtn = document.getElementById('btn-confirm-yes');
    const noBtn = document.getElementById('btn-confirm-no');
    if (yesBtn && noBtn) {
        const key = e.key.toLowerCase();
        if (key === 'y') {
            e.preventDefault();
            yesBtn.click();
        } else if (key === 'n') {
            e.preventDefault();
            noBtn.click();
        }
    }
});
