import { db } from "../../lib/firebase";
import admin from "firebase-admin";

export default async function handler(req, res) {
  if (req.method !== "GET") {
    return res.status(405).json({ error: "Method not allowed" });
  }

  try {
    // Dữ liệu TheSieuRe gửi về
    const {
      status,
      trans_id,
      declared_value,
      value,
      amount,
      message,
      request_id, // mã giao dịch bạn gửi đi để biết user nào
      telco,      // loại thẻ (Viettel, Mobifone, Vinafone...)
    } = req.query;

    // Ép kiểu dữ liệu
    const statusNum = Number(status);
    const declared = Number(declared_value || 0);
    const realValue = Number(value || 0);
    const realAmount = Number(amount || 0);

    // Ghi log để debug
    console.log("Callback TheSieuRe:", req.query);

    // Bảng quy đổi mệnh giá -> Robux
    const rateMap = {
      10000: 32,
      20000: 64,
      30000: 96,
      50000: 160,
      100000: 320,
      200000: 640,
      500000: 1600,
    };

    // Bảng chiết khấu riêng theo nhà mạng
    const feeMap = {
      viettel: 0.07,
      mobifone: 0.055,
      vinafone: 0.065,
      vietnamobile: 0.20,
      zing: 0.05,
      garena: 0.05,
      vcoin: 0.05,
      scoin: 0.06,
    };

    let robux = 0;
    let thucNhan = 0;

    if (statusNum === 1) {
      // Thành công

      // Tính thực nhận sau khi trừ thêm phí riêng của telco
      const fee = feeMap[telco?.toLowerCase()] || 0; // nếu không có thì không trừ thêm
      thucNhan = realAmount * (1 - fee);

      // Quy đổi robux theo bảng
      if (rateMap[declared]) {
        robux = rateMap[declared];
      }
    }

    // Lưu vào Firestore
    await db.collection("napthe").doc(trans_id).set({
      request_id: request_id || null,
      trans_id,
      telco,
      declared_value: declared,
      value: realValue,
      amount: realAmount,
      thucNhan,
      robux,
      status: statusNum,
      message,
      createdAt: admin.firestore.FieldValue.serverTimestamp(),
    });

    // TODO: cộng robux vào tài khoản user dựa theo request_id
    // if (statusNum === 1 && request_id) {
    //   await db.collection("users").doc(request_id).update({
    //     robux: admin.firestore.FieldValue.increment(robux)
    //   });
    // }

    return res.status(200).json({ success: true, trans_id, status: statusNum });
  } catch (err) {
    console.error("Lỗi xử lý callback:", err);
    return res.status(500).json({ error: "Lỗi server", details: err.message });
  }
}
