# El Peón: Tactical CODEX (Learning Memory)

This document represents the shared persistent memory of the Peon Squad. It tracks project-specific nuances, tactical patterns, and successful solutions to prevent the repetition of past failures.

## 🧠 Core System Intelligence
- **Environment**: Laragon (Windows).
- **Paths**: Use dynamic discovery (`.peon_env`). Avoid hardcoding absolute server paths.
- **Language**: Instructions are English; Final Output must be Spanish.
- **Mode**: PAT (Protocolo de Autonomía Total) is the primary operational mode for heavy repairs.

## 💻 Technical Patterns & Gotchas
### 🏗️ Infrastructure
- **Server**: PHP 8.x. Use `error_log` in project root for diagnostics.
- **Terminal**: Use PowerShell compatible commands or native Windows binaries (`dir`, `tasklist`). Avoid `grep` unless `ripgrep` is confirmed.

### 🔌 Connectivity & Sync
- **Rutas Maestras**: Prioritize `%USERPROFILE%\.gemini\antigravity\skills` (system) and `%PEON_ROOT%\code\skills` (repo).
- **Manual DB Override**: If `.env` fail, use the manual prompt. Fallback to `root`/NUL for localhost.
- **Sync Transparency**: Always preview `PUSH` files. `PULL` performs a mandatory full local backup of the project before updating.
- **Triage**: Always consult the Orquestador for mission direction if the entry point is ambiguous.

## 🛠️ Mission Logs (Lessons Learned)
### [2026-04-17] - Skill Infrastructure Upgrade
- **Discovery**: Agents were wasting tokens on visual checks for code-level errors (500, Syntax).
- **Solution**: Implemented "Log-First" protocol in Centinela de Calidad and Maestro Desarrollador.
- **Learning**: Priority must be: Terminal/Logs -> Code Analysis -> Build/Test -> Visual Verification.

---
*Add new entries after every successful mission.*
