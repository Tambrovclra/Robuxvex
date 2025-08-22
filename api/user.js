export default async function handler(req, res) {
  const { username } = req.query;
  if (!username) return res.status(400).json({ error: "Missing username" });

  try {
    const r = await fetch("https://users.roblox.com/v1/usernames/users", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ usernames: [username] })
    });
    const data = await r.json();
    res.status(200).json(data);
  } catch (err) {
    res.status(500).json({ error: "Roblox API error", details: err.message });
  }
}
