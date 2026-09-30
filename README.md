# BDBlockDisplay

BDBlockDisplay is an Altay + Customies based in-world block display editor for Minecraft Bedrock servers.

## Features

- Display blocks as movable, rotatable and scalable models
- Player-control movement mode
- Move, rotate, scale and exact-value editing
- Group selection and group transforms
- Walkable / solid display mode without placing permanent barrier blocks
- Slab, stair, fence and cube model support
- Vanilla block textures through the bundled resource pack
- Persistent display data and safe cleanup of temporary renderer entities
- Custom editor tools through Customies

## Commands

```text
/bdbd
/bdbd help
/bdbd spawn <block>
/bdbd menu
/bdbd control
/bdbd group
/bdbd grouptool
/bdbd mode <move|rotate|scale|calibrate>
/bdbd solid [on|off|toggle]
/bdbd drag stop
/bdbd delete
/bdbd cleanup
/bdbd list
/bdbd version
/bdbd debug
```

## Custom Tools

```text
bd:wand
bd:wand_rotate
bd:wand_scale
bd:group_tool
```

## Requirements

- Altay 5.x
- Customies

## Plugin Information

- Name: BDBlockDisplay
- Version: 1.0.0
- Main: `BDBlockDisplay\BobyDev\main`
- Author: BobyDev
- License: MIT

## Resource Pack

The plugin includes its required resource pack inside the PHAR. Install and enable the pack on the server/client as required by your server setup.

## Credits

Developed by BobyDev for Altay-based Bedrock servers.
