# 🧊 BDBlockDisplay

**Version:** 1.0.1
**Author:** BobyDev

> ✨ **A powerful in-world block display editor for Minecraft Bedrock servers running Altay.**

BDBlockDisplay lets server administrators create **displayable 3D blocks**, then move, rotate, scale, group, and precisely position them directly inside the world.

Built for **Altay** with bundled Customies editor support, a resource pack, and mobile-friendly controls.

---

## 🌟 Highlights

| Feature | Description |
|---|---|
| 🧱 Block Displays | Turn supported block IDs into editable 3D displays |
| 🎯 Selection | Select displays and open their editor |
| 🕹️ Player Control | Move a display by moving your own player |
| ↔️ Move | Move displays along X / Y / Z |
| 🔄 Rotate | Control X / Y / Z rotation and side lean |
| 📐 Scale | Resize displays with incremental controls |
| 🎯 Exact Values | Enter exact position, rotation and size |
| 👥 Groups | Select multiple displays and transform them together |
| 🚶 Walkable / Solid | Make a display behave as a walkable platform without permanently placing barrier blocks |
| 🧩 Model Variants | Supports cube, slab, stair, fence and related mapped block models |
| 🎨 Resource Pack | Includes the renderer and required textures |
| 🛠️ Custom Tools | Wand, rotation, scale and group tools |
| 💾 Persistence | Display data is stored so displays can survive normal server restarts |
| 🧹 Cleanup | Temporary renderer entities can be cleaned safely |

---

# 🚀 Requirements

- **Altay 5.x**
- No external plugin dependency; the required Customies editor support is bundled in the PHAR
- A Bedrock client compatible with the server's resource-pack setup

### Compatibility target

BDBlockDisplay was developed and checked against the supplied **Altay 5.44.7** source/API and the Customies integration used by the project.

> ⚠️ Always test a new release on a backup/test world before using it on a production server.

---

# 📦 Installation

### 1. Install the plugin

BDBlockDisplay is self-contained and does not require a separate Customies plugin. Install the BDBlockDisplay PHAR directly.

### 2. Place the plugin

Put:

```text
BDBlockDisplay-1.0.1.phar
```

inside:

```text
plugins/
```

### 3. Restart the server

Do a full server restart so Altay can load the plugin and its resource-pack integration cleanly.

### 4. Check the console

You should see BDBlockDisplay load successfully.

---

# 🎮 Commands

## 🏠 Main editor

```text
/bdbd
```

Open the BDBlockDisplay editor.

```text
/bdbd help
```

Show available commands.

---

## 🧱 Spawn a display

```text
/bdbd spawn <block>
```

Examples:

```text
/bdbd spawn stone
/bdbd spawn diamond_block
/bdbd spawn grass
/bdbd spawn oak_planks
/bdbd spawn glass
/bdbd spawn quartz_block
```

The supplied block ID must be available in the BDBlockDisplay mapping.

---

## 🎯 Open the editor

```text
/bdbd menu
```

Open the display editor/menu.

---

## 🕹️ Player Control

```text
/bdbd control
```

Toggle player-control movement.

In Player Control mode, the player's movement is used as the controller for the selected display.

Typical controls:

```text
⬆️ Move forward  → display follows forward
⬇️ Move backward → display follows backward
⬅️ Move left     → display follows left
➡️ Move right    → display follows right
⬆️ Jump          → display moves upward
```

The exact movement direction depends on the active control mode and player orientation.

---

# 🔄 Modes

```text
/bdbd mode move
/bdbd mode rotate
/bdbd mode scale
/bdbd mode calibrate
```

### 🟦 Move

Change the display position.

### 🟣 Rotate

Change X, Y and Z rotation.

### 🟩 Scale

Change display size.

### 🎯 Calibrate

Use the calibration/editor controls when precise placement is required.

---

# 🚶 Solid / Walkable

```text
/bdbd solid on
/bdbd solid off
/bdbd solid toggle
```

### Solid ON

The display receives walkable/collision behavior.

This is designed so the display can act as a platform **without permanently placing visible barrier blocks in the world**.

### Solid OFF

The display is visual-only and does not provide the same walkable collision behavior.

> ℹ️ Collision is implemented through the plugin's display/collision system rather than converting the display into a normal saved Minecraft block.

---

# 🛑 Drag Control

```text
/bdbd drag stop
```

Stops the current active drag/control operation.

---

# 👥 Groups

```text
/bdbd group
/bdbd grouptool
```

Use the group system to select multiple displays and transform them together.

Supported group operations include movement, rotation and scaling through the group editor.

Typical workflow:

```text
1. 🛠️ Select the group tool
2. 🎯 Select displays
3. 👥 Build the group
4. ↔️ Move / rotate / scale
5. 💾 Keep the resulting display positions
```

---

# 🗑️ Delete & Cleanup

```text
/bdbd delete
```

Delete the currently selected display.

```text
/bdbd cleanup
```

Clean temporary/orphaned renderer data.

Use cleanup carefully on production worlds.

---

# 📋 Display List

```text
/bdbd list
```

Show available/loaded displays.

---

# ℹ️ Information & Debugging

```text
/bdbd version
```

Show the installed BDBlockDisplay version.

```text
/bdbd debug
```

Show debugging information useful for troubleshooting.

---

# 🧰 Custom Tools

BDBlockDisplay provides custom editor tools through Customies.

```text
bd:wand
```

🎯 **Main selection / display tool**

Use it to interact with displays and access the editor.

---

```text
bd:wand_rotate
```

🔄 **Rotation tool**

Designed for rotation editing.

---

```text
bd:wand_scale
```

📐 **Scale tool**

Designed for display resizing.

---

```text
bd:group_tool
```

👥 **Group tool**

Used to add/select displays for group editing.

---

# 🎨 Editor

The editor provides controls for:

### 🧱 Block

Change the block represented by the display.

### 📍 Position

Control:

```text
X
Y
Z
```

### 🔄 Rotation

Control:

```text
X
Y
Z
```

### 📐 Size

Control the display scale.

### 🎯 Exact Values

For precision work, enter exact numerical values instead of repeatedly pressing movement buttons.

---

# 📱 Mobile-Friendly Control

BDBlockDisplay is designed with Bedrock/mobile players in mind.

Instead of relying entirely on tiny 3-axis gizmos, the plugin provides:

- 🕹️ Player Control
- 🧭 Menu-based movement
- 🔄 Rotation increments
- 📐 Scale increments
- 🎯 Exact numeric values
- 👥 Group editing

This makes detailed positioning possible even on touch devices.

---

# 🧩 Supported Models

The renderer supports mapped block-model variants including:

- 🧊 Full cubes
- ▰ Slabs
- 🪜 Stairs
- 🧱 Fences
- 🧱 Related block variants
- 🎨 Textured block variants

The exact appearance depends on the block mapping and bundled resource-pack data.

---

# 🖼️ Resource Pack

The plugin includes its required renderer/resource-pack data inside the PHAR.

The resource pack provides the client-side presentation required for the custom display entities and their models/textures.

It contains the renderer assets used by BDBlockDisplay, including:

```text
entity/
models/
render_controllers/
animation_controllers/
animations/
textures/
ui/
texts/
```

Do not remove the bundled resource-pack data from the release package.

---

# 🏗️ Architecture

BDBlockDisplay separates the system into three major parts:

```text
                    BDBlockDisplay
                          │
             ┌────────────┴────────────┐
             │                         │
        Server Logic              Resource Pack
             │                         │
     ┌───────┼────────┐        ┌───────┼────────┐
     │       │        │        │       │        │
   Editor  Control  Storage   Models  Textures  UI
     │       │        │        │       │        │
     └───────┼────────┘        └───────┼────────┘
             │                         │
             └──────────┬──────────────┘
                        ▼
                 Bedrock Display
```

The server stores the display state while the resource pack provides the client-side visual representation.

---

# 💾 Persistence

Display state is intended to survive normal server restarts.

Temporary renderer/interaction entities are handled separately so they do not unnecessarily become permanent world entities.

---

# 🛡️ Safety

BDBlockDisplay is designed to avoid permanently placing invisible barrier blocks just to make a display walkable.

When Solid/Walkable is enabled, the plugin manages the collision behavior for the display rather than replacing the world with hidden permanent blocks.

---

# 🧹 Troubleshooting

## Display is visible but cannot be edited

Check:

```text
/bdbd debug
```

Then verify that:

- the bundled Customies editor support is available.
- BDBlockDisplay is loaded.
- The client has accepted the required resource pack.

---

## Display texture/model looks incorrect

Reconnect to the server and make sure the required resource pack is accepted.

If the server uses multiple packs, check the resource-pack order.

---

## Solid mode does not behave correctly

Check:

```text
/bdbd solid on
```

Then reselect/reload the display.

For world-change related issues, reconnecting or reloading the display can force the client-side display state to synchronize again.

---

## A display becomes orphaned

Use:

```text
/bdbd cleanup
```

---

# 🔐 Permissions

The exact permission nodes are defined by the plugin configuration/registration used by the release.

For administrative servers, restrict BDBlockDisplay editing commands to trusted builders/admins.

---

# 📁 Project Structure

```text
BDBlockDisplay/
│
├── plugin.yml
├── README.md
│
├── src/
│   └── BDBlockDisplay/
│       └── BobyDev/
│           └── main.php
│
└── resources/
    └── BDBlockDisplay_RP.mcpack
```

---

# 📋 Plugin Information

```text
╔══════════════════════════════════╗
║        🧊 BDBlockDisplay         ║
╠══════════════════════════════════╣
║ Version : 1.0.1                  ║
║ Author  : BobyDev                ║
║ Main    : BDBlockDisplay\        ║
║           BobyDev\main           ║
║ API     : Altay 5.x              ║
║ Dependency : None (bundled)           ║
╚══════════════════════════════════╝
```

---

# 👨‍💻 Author

**BobyDev**

Built for Altay-based Minecraft Bedrock servers.

---

# 📜 License

BDBlockDisplay is released under the **MIT License**.

See the project license file for the complete license text.

---

# ⭐ GitHub

If you use BDBlockDisplay:

- ⭐ Star the repository
- 🐛 Report reproducible bugs
- 💡 Suggest improvements
- 🔧 Submit improvements through pull requests
- 📖 Keep the documentation updated

Please include your:

```text
Server software/version
BDBlockDisplay version
Altay version
Client version
Console error/log
Steps to reproduce
```

when reporting a bug.

---

## 💙 BDBlockDisplay

**Build. Move. Rotate. Scale. Display.**

> 🧊 Turn your Bedrock world into a real 3D building workspace.
