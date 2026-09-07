def filter_known_references(items: list[str], known: set[str], label: str) -> tuple[list[str], list[str]]:
    """
    Keeps only references the model was actually shown, dropping (and
    warning about) anything it invented. This never raises: an invented
    reference is stripped and reported, not treated as a hard failure,
    matching the "explicitly say when context is insufficient" rule rather
    than crashing a whole response over one bad reference.
    """
    kept: list[str] = []
    warnings: list[str] = []

    for item in items:
        if item in known:
            kept.append(item)
        else:
            warnings.append(f"AI referenced unknown {label} '{item}' — removed (not present in provided context).")

    return kept, warnings
