// /api/gamepasses.js
export default async function handler(req, res) {
  const { userId } = req.query;
  if (!userId) return res.status(400).json({ error: "Missing userId" });

  try {
    // Lấy vài game của user
    const gamesRes = await fetch(
      `https://games.roblox.com/v2/users/${userId}/games?limit=5`
    );
    const games = await gamesRes.json();
    const gameIds = (games?.data || []).map(g => g.id);
    if (gameIds.length === 0) return res.status(200).json({ data: [] });

    // Lấy gamepass của từng game
    const passLists = await Promise.all(
      gameIds.map(id =>
        fetch(`https://games.roblox.com/v1/games/${id}/game-passes?limit=100`)
          .then(r => r.json())
          .catch(() => ({ data: [] }))
      )
    );
    const passes = passLists.flatMap(p => p?.data || []);
    if (passes.length === 0) return res.status(200).json({ data: [] });

    // Lấy giá từng gamepass (economy) + ghép thumbnail (batch)
    const priced = await Promise.all(
      passes.map(async p => {
        let price = null;
        try {
          const info = await fetch(
            `https://economy.roblox.com/v1/game-pass/${p.id}/product-info`
          ).then(r => r.json());
          if (typeof info?.PriceInRobux === "number") price = info.PriceInRobux;
        } catch {}
        return { id: p.id, name: p.name, price };
      })
    );

    // thumbnail batch
    let thumbs = {};
    try {
      const ids = priced.map(p => p.id).join(",");
      const t = await fetch(
        `https://thumbnails.roblox.com/v1/assets?assetIds=${ids}&size=150x150&format=Png`
      ).then(r => r.json());
      (t?.data || []).forEach(x => (thumbs[x.targetId] = x.imageUrl));
    } catch {}

    const data = priced.map(p => ({
      id: p.id,
      name: p.name,
      price: p.price,
      thumbnail: thumbs[p.id] || null
    }));

    res.status(200).json({ data });
  } catch (e) {
    res.status(500).json({ error: "Roblox API error", details: e.message });
  }
}
