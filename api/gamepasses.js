// /api/gamepasses.js
export default async function handler(req, res) {
  const { userId } = req.query;

  if (!userId) return res.status(400).json({ error: "Missing userId" });

  try {
    const r = await fetch(`https://games.roblox.com/v2/users/${userId}/games?accessFilter=2&limit=10`);
    const data = await r.json();

    // Lấy gameId đầu tiên (ví dụ)
    if (!data.data || data.data.length === 0) {
      return res.status(404).json({ error: "No games found" });
    }

    const gameId = data.data[0].id;

    // Lấy danh sách gamepass trong game
    const gpRes = await fetch(`https://games.roblox.com/v1/games/${gameId}/game-passes?limit=100`);
    const gpData = await gpRes.json();

    res.status(200).json(gpData);
  } catch (err) {
    res.status(500).json({ error: "Roblox API error", details: err.message });
  }
}
