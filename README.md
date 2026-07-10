<p align="center">
	<a href="https://github.com/SyntaxStudiosRE">
		<picture>
			<source srcset="https://raw.githubusercontent.com/pmmp/PocketMine-MP/stable/.github/readme/pocketmine-dark-rgb.gif" media="(prefers-color-scheme: dark)">
			<img src="https://raw.githubusercontent.com/pmmp/PocketMine-MP/stable/.github/readme/pocketmine-rgb.gif" loading="eager" />
		</picture>
	</a><br>
	<b>SyntaxStudios' fork of PocketMine-MP — a highly customisable server software for Minecraft: Bedrock Edition written in PHP</b>
</p>

<p align="center">
	<a href="https://github.com/SyntaxStudiosRE/PocketMine-MP/actions/workflows/main.yml"><img src="https://github.com/SyntaxStudiosRE/PocketMine-MP/actions/workflows/main.yml/badge.svg" alt="CI" /></a>
	<a href="https://github.com/SyntaxStudiosRE/PocketMine-MP/releases/latest"><img alt="GitHub release (latest SemVer)" src="https://img.shields.io/github/v/release/SyntaxStudiosRE/PocketMine-MP?label=release&sort=semver"></a>
</p>

## What is this?
This is [SyntaxStudios](https://github.com/SyntaxStudiosRE)' maintained fork of **PocketMine-MP**, a highly customisable server software for Minecraft: Bedrock Edition, built from scratch in PHP.

We maintain this fork to power our own Bedrock servers (including **Legacy**). It's based on [NetherGamesMC's multi-protocol fork](https://github.com/NetherGamesMC/PocketMine-MP), which in turn descends from the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP).

- 🧩 **Powerful plugin API** - extend and customise gameplay as you see fit
- 🌐 **Multi-world support** - offer a more varied game experience to players without transferring them to other server nodes
- 🏎️ **Performance** - get 100+ players onto one server (depending on hardware and plugins)
- 🔀 **Multi-protocol** - inherited from NetherGamesMC, supports multiple Bedrock client versions at once

## :x: PocketMine-MP is NOT a vanilla Minecraft server software.
**It is poorly suited to hosting vanilla survival servers.**
It doesn't have many features from the vanilla game, such as vanilla world generation, redstone, mob AI, and various other things.

## Attribution & lineage
This project would not exist without the work of the original **PMMP team** (in particular `dktapps` and `shoghicp`) and the **NetherGamesMC** development team, who maintain the multi-protocol fork this repository is based on.

`pmmp/PocketMine-MP` was archived on 2026-07-09 after the team announced an end of support, having left [documentation on the protocol update process](https://doc.pmmp.io/en/rtfd/developers/internals-docs/updating-minecraft-protocol.html) for anyone wishing to continue the work. NetherGamesMC's fork remains actively maintained and is what this repository tracks.

## Building & Contributing
This is an internal fork maintained for SyntaxStudios' own infrastructure. See [BUILDING.md](BUILDING.md) for build instructions.

## Licensing information
This project is licensed under **LGPL-3.0**, inherited unchanged from the upstream project. Please see the [LICENSE](/LICENSE) file for details.

SyntaxStudios, NetherGamesMC and pmmp/PocketMine are not affiliated with Mojang. All brands and trademarks belong to their respective owners. This software is not Mojang-approved, nor associated with Mojang.
