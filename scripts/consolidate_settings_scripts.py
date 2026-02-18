#!/usr/bin/env python3
"""
Consolidate settings.php: Merge 8 inline <script> blocks into 2.

Block #1 (Flash dismiss, L31-46): STAYS — PHP conditional
Blocks #2-8: All consolidated into ONE <script> at the bottom

Result: 2 script blocks instead of 8.
"""

import sys
import os

filepath = os.path.join(os.path.dirname(__file__), '..', 'app', 'Views', 'settings.php')
filepath = os.path.abspath(filepath)

with open(filepath, 'r') as f:
    content = f.read()
    lines = content.split('\n')

print(f"Read {len(lines)} lines from settings.php")

# Verify key markers exist
assert '<script>' in lines[30], f"Expected <script> at line 31, got: {lines[30]}"
assert '</script>' in lines[45], f"Expected </script> at line 46, got: {lines[45]}"

# ── Identify block boundaries (0-indexed) ──
# Block 1: L31-46 (idx 30-45) — Flash dismiss — KEEP
# Block 2: L983-1009 (idx 982-1008) — WhatsApp toggle
# Block 3: L1410-1481 (idx 1409-1480) — Template tabs
# Block 4: L1645-1673 (idx 1644-1672) — Helpers
# Block 5: L1675-2054 (idx 1674-2053) — Blocked periods
# Block 6: L2058-2767 (idx 2057-2766) — Main init
# Block 7: L2770-2783 (idx 2769-2782) — Time format
# Block 8: L2786-3128 (idx 2785-3127) — Database tab

# Verify block starts
for idx, expected_fragment in [
    (982,  '<script>'),
    (1008, '</script>'),
    (1409, '<script>'),
    (1480, '</script>'),
    (1644, '<script>'),
    (1672, '</script>'),
    (1674, '<script>'),
    (2053, '</script>'),
    (2057, '<script>'),
    (2766, '</script>'),
    (2769, '<script>'),
    (2782, '</script>'),
    (2785, '<script>'),
    (3127, '</script>'),
]:
    line = lines[idx].strip()
    if expected_fragment not in line:
        print(f"WARNING: Line {idx+1} expected '{expected_fragment}', got: '{line}'")
        sys.exit(1)

print("All block boundaries verified ✓")

# ── Extract JS code (without <script>/</ script> tags) ──

def extract_js(line_list, start_idx, end_idx):
    """Extract lines between <script> and </script>, exclusive of those tags."""
    # start_idx is the line with <script>, end_idx is the line with </script>
    return line_list[start_idx + 1 : end_idx]

block2_js = extract_js(lines, 982, 1008)
block3_js = extract_js(lines, 1409, 1480)
block4_js = extract_js(lines, 1644, 1672)
block5_js = extract_js(lines, 1674, 2053)
block6_js = extract_js(lines, 2057, 2766)
block7_js = extract_js(lines, 2769, 2782)
block8_js = extract_js(lines, 2785, 3127)

print(f"Block 2 (WhatsApp): {len(block2_js)} lines")
print(f"Block 3 (Templates): {len(block3_js)} lines")
print(f"Block 4 (Helpers): {len(block4_js)} lines")
print(f"Block 5 (Blocked periods): {len(block5_js)} lines")
print(f"Block 6 (Main init): {len(block6_js)} lines")
print(f"Block 7 (Time format): {len(block7_js)} lines")
print(f"Block 8 (Database): {len(block8_js)} lines")

# ── Build consolidated script block ──
consolidated = []
consolidated.append('        <script>')
consolidated.append('        // ═══════════════════════════════════════════════════════════')
consolidated.append('        // Settings Page — Consolidated Script')
consolidated.append('        // Helpers, WhatsApp toggle, template tabs, blocked periods,')
consolidated.append('        // main API init, time format, and database tab.')
consolidated.append('        // ═══════════════════════════════════════════════════════════')
consolidated.append('')
consolidated.append('        // ─── Shared Helpers ─────────────────────────────────────')
for line in block4_js:
    consolidated.append(line)
consolidated.append('')
consolidated.append('        // ─── WhatsApp Provider Toggle ───────────────────────────')
for line in block2_js:
    consolidated.append(line)
consolidated.append('')
consolidated.append('        // ─── Notification Template Tabs ─────────────────────────')
for line in block3_js:
    consolidated.append(line)
consolidated.append('')
consolidated.append('        // ─── Blocked Periods Structured UI ──────────────────────')
for line in block5_js:
    consolidated.append(line)
consolidated.append('')
consolidated.append('        // ─── Main Settings API Init ─────────────────────────────')
for line in block6_js:
    consolidated.append(line)
consolidated.append('')
consolidated.append('        // ─── Time Format Handler ────────────────────────────────')
for line in block7_js:
    consolidated.append(line)
consolidated.append('')
consolidated.append('        // ─── Database Settings Tab ──────────────────────────────')
for line in block8_js:
    consolidated.append(line)
consolidated.append('        </script>')

print(f"\nConsolidated block: {len(consolidated)} lines")

# ── Reconstruct file ──
# Strategy:
# 1. Keep everything up to (excluding) Block #2 script tag
# 2. Skip Block #2 script (L983-L1009 = idx 982-1008)
# 3. Keep everything between Block #2 end and Block #3 start
# 4. Skip Block #3 script (L1410-L1481 = idx 1409-1480)
# 5. Keep everything between Block #3 end and Block #4 start
# 6. Skip Blocks #4+5 (L1645-L2054 = idx 1644-2053)
# 7. Keep HTML between Block #5 and Block #6 (the </div></div>)
# 8. Replace Blocks #6+7+8 with consolidated block
# 9. Keep the endSection line

new_lines = []

# Part 1: Start through end of Block #2's preceding HTML (up to idx 981)
new_lines.extend(lines[0:982])

# Part 2: Skip Block #2 (idx 982-1008), continue from idx 1009
new_lines.extend(lines[1009:1409])

# Part 3: Skip Block #3 (idx 1409-1480), continue from idx 1481
new_lines.extend(lines[1481:1644])

# Part 4: Skip Blocks #4 and #5 (idx 1644-2053), keep the HTML between #5 and #6
# Lines 2054-2056 (idx 2053-2055) are:
#   L2054: "        </script>"  — this is Block #5's closing tag, SKIP
#   L2055: "    </div>"         — HTML, KEEP
#   L2056: "</div>"             — HTML, KEEP
new_lines.extend(lines[2054:2057])  # idx 2054 = L2055, idx 2055 = L2056, idx 2056 = L2057 (empty)

# Part 5: Skip Blocks #6, #7, #8 (idx 2057-3127), insert consolidated
# But wait — the </script> of block 5 is at idx 2053 (L2054), and the HTML is at idx 2054-2056
# Let me re-check what's at each index

# idx 2053 = L2054: "        </script>"   — Block #5 closing, already included in "skip"
# idx 2054 = L2055: "    </div>"
# idx 2055 = L2056: "</div>"
# idx 2056 = L2057: ""  (empty line)
# idx 2057 = L2058: "        <script>"    — Block #6 opening

# So after skipping blocks 4+5 (idx 1644-2053 inclusive), I need to keep idx 2054-2056

new_lines = []  # Reset and redo more carefully

# Section A: Lines before Block #2 (idx 0 to 981 inclusive)
new_lines.extend(lines[0:982])

# Section B: Skip Block #2 (idx 982 to 1008 inclusive)
# Continue from idx 1009

# Section C: Lines between Block #2 and Block #3 (idx 1009 to 1408 inclusive)
new_lines.extend(lines[1009:1409])

# Section D: Skip Block #3 (idx 1409 to 1480 inclusive)
# Continue from idx 1481

# Section E: Lines between Block #3 and Block #4 (idx 1481 to 1643 inclusive)
new_lines.extend(lines[1481:1644])

# Section F: Skip Blocks #4 and #5 (idx 1644 to 2053 inclusive)
# These are the <script>helpers</script><script>blocked periods</script>

# Section G: HTML between Block #5 end and Block #6 start
# idx 2054 = "    </div>"
# idx 2055 = "</div>"
# idx 2056 = ""  (empty line)
new_lines.extend(lines[2054:2057])

# Section H: Skip Blocks #6, #7, #8 (idx 2057 to 3127 inclusive)
# Insert consolidated block instead
new_lines.extend(consolidated)

# Section I: Everything after Block #8 (idx 3128 onwards)
# idx 3128 = L3129: "<?= $this->endSection() ?>"
# idx 3129 = L3130: "" (possibly empty/final line)
new_lines.extend(lines[3128:])

print(f"\nOriginal: {len(lines)} lines")
print(f"New: {len(new_lines)} lines")
print(f"Reduction: {len(lines) - len(new_lines)} lines")

# Write the file
new_content = '\n'.join(new_lines)
with open(filepath, 'w') as f:
    f.write(new_content)

print(f"\n✓ Wrote consolidated settings.php ({len(new_lines)} lines)")

# Verify the result has exactly 2 <script> blocks
script_count = new_content.count('<script>')
print(f"Script block count: {script_count}")
if script_count == 2:
    print("✓ Exactly 2 script blocks (flash dismiss + consolidated)")
else:
    print(f"⚠ Expected 2 script blocks, got {script_count}")
