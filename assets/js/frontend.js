// Vô hiệu hóa tính năng nhấp 2 - 3 lần (double/triple-tap) phóng to màn hình trên Mobile
// Vẫn giữ nguyên khả năng kéo/thu bằng 2 ngón tay (Pinch-to-zoom)
(function disableDoubleTapZoom() {
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function (event) {
        if (event.touches && event.touches.length > 0) return;
        const now = Date.now();
        if (now - lastTouchEnd <= 300) {
            const tag = (event.target && event.target.tagName) ? event.target.tagName.toUpperCase() : '';
            if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
                event.preventDefault();
            }
        }
        lastTouchEnd = now;
    }, { passive: false });
})();

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkin-form');
    const submitBtn = document.getElementById('btn-submit');
    const spinner = document.getElementById('spinner');
    const btnText = document.getElementById('btn-text');
    const alertBox = document.getElementById('alert-message');
    const formBody = document.getElementById('form-fields');

    if (!form) return;

    // Reset Form về trạng thái ban đầu
    window.resetCheckinForm = function () {
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
        alertBox.innerHTML = '';

        if (formBody) formBody.style.display = 'block';
        if (submitBtn) {
            submitBtn.style.display = 'block';
            submitBtn.disabled = false;
        }
        if (spinner) spinner.style.display = 'none';
        if (btnText) btnText.textContent = 'Xác nhận Check-in';

        const actionInput = document.getElementById('form-action-type');
        if (actionInput) actionInput.value = 'lookup';
        
        const guestIdInput = document.getElementById('form-guest-id');
        if (guestIdInput) guestIdInput.value = '';

        const codeInput = document.getElementById('customer_code');
        if (codeInput) {
            codeInput.value = '';
            codeInput.focus();
        }
    };

    function triggerHapticAndFlash(type) {
        try {
            if (navigator.vibrate) {
                if (type === 'success') navigator.vibrate([100, 50, 100]);
                else if (type === 'warning') navigator.vibrate([150]);
                else if (type === 'error') navigator.vibrate([250, 80, 250]);
            }
        } catch (e) {}

        const container = document.querySelector('.container') || document.body;
        if (container) {
            container.classList.remove('scan-flash-success', 'scan-flash-warning', 'scan-flash-error');
            void container.offsetWidth;
            container.classList.add(`scan-flash-${type}`);
        }
    }

    // Xử lý gửi Form
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Reset cảnh báo
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
        alertBox.innerHTML = '';

        // Khóa nút
        submitBtn.disabled = true;
        if (spinner) spinner.style.display = 'inline-block';
        if (btnText) btnText.textContent = 'Đang tra cứu...';

        const formData = new FormData(form);

        try {
            const targetUrl = form.getAttribute('action') || 'api/checkin.php';
            const response = await fetch(targetUrl, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });

            const text = await response.text();
            let data = null;
            try {
                data = JSON.parse(text);
            } catch (jsonErr) {
                console.error('Phản hồi từ server không phải JSON chuẩn:', text);
                if (text.includes('Not Found') || text.includes('404')) {
                    throw new Error('Đã có bản cập nhật mới. Vui lòng nhấn F5 (Tải lại trang) để áp dụng!');
                }
                throw new Error('Lỗi phản hồi máy chủ: ' + text);
            }

            // Ẩn form nhập liệu khi hiển thị popup/kết quả
            if (formBody) formBody.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';

            if (data.status === 'not_found') {
                triggerHapticAndFlash('error');
                // 1. Không tìm thấy mã khách hàng trong CSDL
                alertBox.style.display = 'block';
                alertBox.className = 'alert error';
                alertBox.style.background = 'transparent';
                alertBox.style.padding = '0';
                alertBox.style.border = 'none';

                alertBox.innerHTML = `
                    <div style="background: #ffffff; border: 2px solid #ef4444; border-radius: 18px; padding: 24px 20px; box-shadow: 0 12px 30px rgba(239, 68, 68, 0.15); text-align: center;">
                        <div style="font-weight: 800; color: #b91c1c; font-size: 1.1rem; margin-bottom: 8px;">
                            ${data.message || 'Mã khách hàng không tồn tại trong hệ thống.'}
                        </div>
                        <div style="font-size: 0.88rem; color: #64748b; margin-bottom: 20px;">
                            Vui lòng kiểm tra lại mã do Nhà Phân Phối cung cấp.
                        </div>
                        <button type="button" onclick="window.resetCheckinForm()" style="background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); color: white; border: none; padding: 12px 28px; border-radius: 10px; font-weight: 800; font-size: 0.95rem; cursor: pointer; box-shadow: 0 4px 14px rgba(211, 47, 47, 0.3); transition: all 0.2s ease;">
                            Nhập lại Mã KH
                        </button>
                    </div>
                `;
            } else if (data.status === 'require_guest_confirmation') {
                triggerHapticAndFlash('warning');
                // 2. Tìm thấy Mã hợp lệ -> Hiện Modal Xác nhận Sang Trọng (Chỉ gồm Mã KH và Đơn vị / Đại lý)
                alertBox.style.display = 'block';
                alertBox.className = 'alert warning';
                alertBox.style.background = 'transparent';
                alertBox.style.padding = '0';
                alertBox.style.border = 'none';

                const gInfo = data.data;

                alertBox.innerHTML = `
                    <div style="background: #ffffff; border: 1.5px solid #d32f2f; border-radius: 18px; padding: 24px 20px; box-shadow: 0 12px 30px rgba(211, 47, 47, 0.12); text-align: center;">
                        <div style="font-size: 1.15rem; font-weight: 800; color: #b71c1c; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                            XÁC NHẬN THÔNG TIN KHÁCH HÀNG
                        </div>
                        <div style="font-size: 0.88rem; color: #64748b; margin-bottom: 20px;">
                            Quý khách vui lòng kiểm tra thông tin dưới đây:
                        </div>

                        <div style="background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 12px; padding: 18px; text-align: left; margin-bottom: 20px;">
                            <div style="margin-bottom: 12px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: #7f1d1d; font-weight: 600;">Mã KH:</span>
                                <strong style="color: #b71c1c; background: #ffffff; border: 1.5px solid #fca5a5; padding: 4px 14px; border-radius: 8px; font-weight: 800; font-size: 1.1rem;">${gInfo.customer_code}</strong>
                            </div>
                            <div style="font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: #7f1d1d; font-weight: 600;">Đơn vị / Đại lý:</span>
                                <strong style="color: #1e293b; font-weight: 700; font-size: 1.05rem;">${gInfo.organization}</strong>
                            </div>
                        </div>

                        <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.4;">
                            Thông tin trên có phải là thông tin của Quý khách không?
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <button type="button" id="btn-confirm-yes" style="background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); color: #ffffff; border: none; padding: 15px; border-radius: 12px; font-weight: 800; font-size: 1.02rem; cursor: pointer; box-shadow: 0 6px 18px rgba(211, 47, 47, 0.35); transition: all 0.2s ease;">
                                Đúng thông tin của tôi - Xác nhận Check-in
                            </button>
                            <button type="button" onclick="window.resetCheckinForm()" style="background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.92rem; cursor: pointer; transition: all 0.2s ease;">
                                Không phải tôi - Nhập lại
                            </button>
                        </div>
                    </div>
                `;

                // Bấm nút "Đúng thông tin của tôi" -> Gửi request xác nhận Check-in
                document.getElementById('btn-confirm-yes').addEventListener('click', () => {
                    const actionInput = document.getElementById('form-action-type');
                    const guestIdInput = document.getElementById('form-guest-id');
                    if (actionInput) actionInput.value = 'confirm_checkin';
                    if (guestIdInput) guestIdInput.value = gInfo.guest_id;
                    
                    form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
                });

            } else if (data.status === 'already_checked_in') {
                triggerHapticAndFlash('warning');
                // 3. Khách đã check-in trước đó
                alertBox.style.display = 'block';
                alertBox.className = 'alert info';
                alertBox.style.background = 'transparent';
                alertBox.style.padding = '0';
                alertBox.style.border = 'none';

                const resData = data.data;

                alertBox.innerHTML = `
                    <div style="background: #ffffff; border: 2px solid #0284c7; border-radius: 18px; padding: 24px 20px; box-shadow: 0 12px 35px rgba(2, 132, 199, 0.15); text-align: center;">
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0284c7; margin-bottom: 6px;">
                            Quý khách đã check-in trước đó!
                        </div>
                        <div style="font-size: 0.88rem; color: #64748b; margin-bottom: 20px;">
                            Thời gian ghi nhận: <strong>${resData.checkin_time}</strong>
                        </div>

                        <div style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 12px; padding: 14px 18px; text-align: left; margin-bottom: 20px;">
                            <div style="margin-bottom: 8px; font-size: 0.92rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #0369a1; font-weight: 600;">Mã KH:</span>
                                <strong style="color: #0284c7; font-weight: 800; font-size: 1.05rem;">${resData.customer_code}</strong>
                            </div>
                            <div style="font-size: 0.92rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #0369a1; font-weight: 600;">Đơn vị / Đại lý:</span>
                                <strong style="color: #0f172a; font-weight: 700; font-size: 1rem;">${resData.organization}</strong>
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 14px; justify-content: center;">
                            ${resData.table_name ? `
                                <div style="flex: 1; min-width: 140px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2.5px solid #2e7d32; border-radius: 16px; padding: 14px 12px; text-align: center; box-shadow: 0 6px 18px rgba(46, 125, 50, 0.18);">
                                    <div style="font-size: 0.78rem; color: #1b5e20; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">VỊ TRÍ BÀN NGỒI</div>
                                    <div style="font-size: 1.5rem; font-weight: 900; color: #1b5e20;">${resData.table_name}</div>
                                </div>
                            ` : ''}
                            
                            ${resData.lucky_draw_code ? `
                                <div style="flex: 1; min-width: 140px; background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); border: 2.5px solid #7b1fa2; border-radius: 16px; padding: 14px 12px; text-align: center; box-shadow: 0 6px 18px rgba(123, 31, 162, 0.18);">
                                    <div style="font-size: 0.78rem; color: #4a148c; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">MÃ TRÚNG THƯỞNG</div>
                                    <div style="font-size: 1.5rem; font-weight: 900; color: #4a148c;">${resData.lucky_draw_code}</div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else if (data.status === 'success') {
                triggerHapticAndFlash('success');
                // 4. CHECK-IN THÀNH CÔNG!
                try { localStorage.setItem('checkin_realtime_signal', Date.now().toString()); } catch(e) {}
                if (window.playNotifChime) window.playNotifChime();

                alertBox.style.display = 'block';
                alertBox.className = 'alert success';
                alertBox.style.background = 'transparent';
                alertBox.style.padding = '0';
                alertBox.style.border = 'none';

                const resData = data.data;

                alertBox.innerHTML = `
                    <div style="background: #ffffff; border: 2px solid #22c55e; border-radius: 20px; padding: 26px 20px; box-shadow: 0 15px 40px rgba(34, 197, 94, 0.18); text-align: center;">
                        <div style="font-size: 1.4rem; font-weight: 900; color: #15803d; letter-spacing: 0.5px; margin-bottom: 8px;">
                            CHECK-IN THÀNH CÔNG
                        </div>
                        <div style="font-size: 0.95rem; color: #166534; font-weight: 600; margin-bottom: 22px; line-height: 1.5; padding: 0 8px;">
                            Sự hiện diện của Quý khách là niềm vinh hạnh lớn cho chúng tôi. Xin trân trọng cảm ơn!
                        </div>

                        <!-- Thẻ thông tin khách hàng -->
                        <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 14px 18px; text-align: left; margin-bottom: 22px;">
                            <div style="margin-bottom: 8px; font-size: 0.92rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #166534; font-weight: 600;">Mã KH:</span>
                                <strong style="color: #15803d; font-weight: 800; font-size: 1.05rem;">${resData.customer_code}</strong>
                            </div>
                            <div style="font-size: 0.92rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #166534; font-weight: 600;">Đơn vị / Đại lý:</span>
                                <strong style="color: #0f172a; font-weight: 700; font-size: 1rem;">${resData.organization}</strong>
                            </div>
                        </div>

                        <!-- Thẻ NỔI BẬT Vị Trí Bàn & Mã Trúng Thưởng -->
                        <div style="display: flex; flex-wrap: wrap; gap: 14px; justify-content: center;">
                            ${resData.table_name ? `
                                <div style="flex: 1; min-width: 140px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2.5px solid #2e7d32; border-radius: 16px; padding: 16px 12px; text-align: center; box-shadow: 0 8px 20px rgba(46, 125, 50, 0.2);">
                                    <div style="font-size: 0.78rem; color: #1b5e20; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">VỊ TRÍ BÀN NGỒI</div>
                                    <div style="font-size: 1.6rem; font-weight: 900; color: #1b5e20;">${resData.table_name}</div>
                                </div>
                            ` : ''}
                            
                            ${resData.lucky_draw_code ? `
                                <div style="flex: 1; min-width: 140px; background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); border: 2.5px solid #7b1fa2; border-radius: 16px; padding: 16px 12px; text-align: center; box-shadow: 0 8px 20px rgba(123, 31, 162, 0.2);">
                                    <div style="font-size: 0.78rem; color: #4a148c; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">MÃ TRÚNG THƯỞNG</div>
                                    <div style="font-size: 1.6rem; font-weight: 900; color: #4a148c;">${resData.lucky_draw_code}</div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                // Phản hồi lỗi chung
                alertBox.style.display = 'block';
                alertBox.className = 'alert error';
                alertBox.style.background = 'transparent';
                alertBox.style.padding = '0';
                alertBox.style.border = 'none';

                alertBox.innerHTML = `
                    <div style="background: #ffffff; border: 2px solid #ef4444; border-radius: 16px; padding: 20px; text-align: center; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.12);">
                        <div style="font-weight: 700; color: #b91c1c; margin-bottom: 14px;">${data.message || 'Đã có lỗi xảy ra. Vui lòng thử lại!'}</div>
                        <button type="button" onclick="window.resetCheckinForm()" style="background: #ef4444; color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 700; cursor: pointer;">Nhập lại</button>
                    </div>
                `;
            }
        } catch (e) {
            console.error('Checkin error:', e);
            
            if (formBody) formBody.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';

            alertBox.style.display = 'block';
            alertBox.className = 'alert error';
            alertBox.style.background = 'transparent';
            alertBox.style.padding = '0';
            alertBox.style.border = 'none';

            alertBox.innerHTML = `
                <div style="background: #ffffff; border: 2px solid #ef4444; border-radius: 16px; padding: 20px; text-align: center; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.12);">
                    <div style="font-weight: 700; color: #b91c1c; margin-bottom: 14px;">${e.message || 'Không thể kết nối máy chủ. Vui lòng thử lại!'}</div>
                    <button type="button" onclick="window.resetCheckinForm()" style="background: #ef4444; color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 700; cursor: pointer;">Thử lại</button>
                </div>
            `;
        }
    });
});
