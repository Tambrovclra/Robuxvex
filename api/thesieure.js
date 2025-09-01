import admin from "firebase-admin";

if (!admin.apps.length) {
  admin.initializeApp({
    credential: admin.credential.cert(
      JSON.parse(process.env.FIREBASE_SERVICE_ACCOUNT_KEY)
    ),
  });
}

const db = admin.firestore();

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
      request_id, // user nào nạp
      telco,      // loại thẻ
    } = req.query;

    const statusNum = Number(status);
    const declared = Number(declared_value || 0);
    const realValue = Number(value || 0);
    const realAmount = Number(amount || 0);

    // Bảng chiết khấu riêng
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

    let thucNhan = 0;

    if (statusNum === 1) {
      const fee = feeMap[telco?.toLowerCase()] || 0;
      thucNhan = realAmount * (1 - fee);
    }

    // Lưu log vào Firestore
    await db.collection("napthe").doc(trans_id).set({
      request_id: request_id || null,
      trans_id,
      telco,
      declared_value: declared,
      value: realValue,
      amount: realAmount,
      thucNhan,
      status: statusNum,
      message,
      createdAt: admin.firestore.FieldValue.serverTimestamp(),
    });

    return res.status(200).json({ success: true, trans_id, thucNhan, status: statusNum });
  } catch (err) {
    console.error("Lỗi xử lý callback:", err);
    return res.status(500).json({ error: "Lỗi server", details: err.message });
  }
}
