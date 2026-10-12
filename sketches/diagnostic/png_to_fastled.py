from PIL import Image

img = Image.open("lightboard.png").convert("RGB")

w, h = img.size

print(f"// {w}x{h}")
print("CRGB image[48][48] = {")

for y in range(h):
    print(" {", end="")

    for x in range(w):
        r, g, b = img.getpixel((x, y))

        print(
            f"CRGB({r},{g},{b})",
            end=""
        )

        if x != w - 1:
            print(",", end="")

    print(" }" + ("," if y != h - 1 else ""))

print("};")