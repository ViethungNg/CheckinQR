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
                            <div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">
                                👤 <strong>Họ tên:</strong> ${data.data.full_name}
                            </div>
                            <div style="margin-bottom: 14px; font-size: 0.95rem; color: #333;">
                                📞 <strong>Số điện thoại:</strong> ${data.data.phone}
                            </div>
                            
                            <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 10px;">
                                ${data.data.table_name ? `
                                    <div style="flex: 1; min-width: 140px; background: #e8f5e9; border: 2px solid #66bb6a; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(46,125,50,0.12);">
                                        <div style="font-size: 0.75rem; color: #2e7d32; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🪑 Vị trí ngồi</div>
                                        <div style="font-size: 1.25rem; font-weight: 800; color: #1b5e20;">${data.data.table_name}</div>
                                    </div>
                                ` : ''}
                                
                                ${data.data.lucky_draw_code ? `
                                    <div style="flex: 1; min-width: 140px; background: #f3e5f5; border: 2px solid #ab47bc; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(123,31,162,0.12);">
                                        <div style="font-size: 0.75rem; color: #7b1fa2; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🎟️ Mã bốc thăm</div>
                                        <div style="font-size: 1.25rem; font-weight: 800; color: #4a148c;">${data.data.lucky_draw_code}</div>
                                    </div>
                                ` : ''}
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
                        extraMsg = `
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #81c784; text-align: center;">
                                <div style="font-size: 0.95rem; color: #2e7d32; font-weight: 600; margin-bottom: 12px; line-height: 1.5;">
                                    🎉 Sự hiện diện của Quý khách là niềm vinh hạnh của chúng tôi. Chúc Quý khách có một buổi hội nghị tràn đầy năng lượng và gặt hái nhiều thành công!
                                </div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 10px;">
                                    ${data.data.table_name ? `
                                        <div style="flex: 1; min-width: 140px; background: #e8f5e9; border: 2px solid #66bb6a; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(46,125,50,0.12);">
                                            <div style="font-size: 0.75rem; color: #2e7d32; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🪑 Vị trí ngồi</div>
                                            <div style="font-size: 1.25rem; font-weight: 800; color: #1b5e20;">${data.data.table_name}</div>
                                        </div>
                                    ` : ''}
                                    
                                    ${data.data.lucky_draw_code ? `
                                        <div style="flex: 1; min-width: 140px; background: #f3e5f5; border: 2px solid #ab47bc; border-radius: 12px; padding: 10px 14px; text-align: center; box-shadow: 0 3px 8px rgba(123,31,162,0.12);">
                                            <div style="font-size: 0.75rem; color: #7b1fa2; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🎟️ Mã bốc thăm</div>
                                            <div style="font-size: 1.25rem; font-weight: 800; color: #4a148c;">${data.data.lucky_draw_code}</div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    } else if (data.data.match_status === 'walk_in') {
                        extraMsg = `
                            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed #ef5350;">
                                <div style="font-weight: bold; color: #c62828; margin-bottom: 12px; font-size: 1rem; line-height: 1.4;">
                                    ⚠️ Thông tin của quý khách vừa nhập không nằm trong danh sách của Ban tổ chức. Vui lòng liên hệ với lễ tân để được hỗ trợ!
                                </div>
                                
                                <div style="background: #ffffff; border: 1px solid #ffcdd2; border-radius: 12px; padding: 14px; text-align: left; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                    <div style="font-size: 0.8rem; color: #777; margin-bottom: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                                        📋 THÔNG TIN VỪA NHẬP (LIÊN HỆ LỄ TÂN HỖ TRỢ)
                                    </div>
                                    <div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">
                                        👤 <strong>Họ tên:</strong> ${data.data.full_name || ''}
                                    </div>
                                    <div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">
                                        📞 <strong>Số điện thoại:</strong> ${data.data.phone || ''}
                                    </div>
                                    ${data.data.address ? `<div style="margin-bottom: 8px; font-size: 0.95rem; color: #333;">🏠 <strong>Địa chỉ:</strong> ${data.data.address}</div>` : ''}
                                    ${data.data.lucky_draw_code ? `<div style="margin-bottom: 8px; font-size: 0.95rem; color: #d32f2f;">🎁 <strong>Mã trúng giải:</strong> ${data.data.lucky_draw_code}</div>` : ''}
                                </div>
                            </div>
                        `;
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
