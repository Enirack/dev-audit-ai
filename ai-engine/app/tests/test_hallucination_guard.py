from app.validation.hallucination_guard import filter_known_references


def test_known_references_are_kept_with_no_warnings() -> None:
    kept, warnings = filter_known_references(["a.py", "b.py"], {"a.py", "b.py"}, "file")

    assert kept == ["a.py", "b.py"]
    assert warnings == []


def test_unknown_reference_is_dropped_and_warned() -> None:
    kept, warnings = filter_known_references(["a.py", "invented.py"], {"a.py"}, "file")

    assert kept == ["a.py"]
    assert len(warnings) == 1
    assert "invented.py" in warnings[0]


def test_empty_input_returns_empty_output() -> None:
    kept, warnings = filter_known_references([], {"a.py"}, "file")

    assert kept == []
    assert warnings == []
